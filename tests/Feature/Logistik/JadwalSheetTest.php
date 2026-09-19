<?php

namespace Tests\Feature\Logistik;

use App\Enums\RoleName;
use App\Models\LogistikJadwalRow;
use App\Models\Unit;
use App\Support\LogistikJadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalSheetTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    private function routeFor(string $sheet, string $action, array $query = []): string
    {
        $menu = LogistikJadwal::sheet($sheet)['menu'];

        return route("logistik.{$menu}.sheet.{$action}", ['jadwal' => $sheet, ...$query]);
    }

    public function test_every_sheet_opens_with_its_default_rows_and_prints_a_pdf(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        foreach (array_keys(LogistikJadwal::SHEETS) as $sheet) {
            $this->actingAs($user)
                ->get($this->routeFor($sheet, 'index', $period))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('logistik/jadwal/sheet')
                    ->where('sheet.key', $sheet)
                    ->where('has_saved', false)
                    ->where('rows', fn ($rows): bool => count($rows) > 0),
                );

            $response = $this->actingAs($user)->get($this->routeFor($sheet, 'pdf', $period));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $reader = new Fpdi;
            $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
            $this->assertSame(LogistikJadwal::orientation($sheet) === 'portrait' ? 'P' : 'L', $reader->getTemplateSize($reader->importPage(1))['orientation'], $sheet);
        }
    }

    public function test_a_sheet_is_only_served_under_its_own_menu(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);

        $this->actingAs($user)->get('/logistik/jadwal/patrol-check')->assertNotFound();
        $this->actingAs($user)->get('/logistik/input/lembar/kegiatan')->assertNotFound();
        $this->actingAs($user)->get('/logistik/jadwal/tidak-ada')->assertNotFound();
    }

    public function test_kegiatan_rows_are_saved_with_their_progress(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post($this->routeFor('kegiatan', 'store'), $period + ['rows' => [
                ['section' => 'mesin', 'nama' => 'Absensi', 'days' => ['3' => 'R', '4' => 'D', '40' => 'D'], 'target' => 4],
                ['section' => 'non-rutin', 'nama' => '', 'days' => []],
            ]])
            ->assertRedirect();

        $rows = LogistikJadwalRow::query()->where('unit_id', $unit->id)->where('jadwal', 'kegiatan')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(['3' => 'R', '4' => 'D'], $rows[0]->days);

        $this->actingAs($user)
            ->get($this->routeFor('kegiatan', 'index', $period))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('rows.0.summary', ['rencana' => 2, 'realisasi' => 1, 'target' => 4, 'kinerja' => '25%']),
            );
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->post($this->routeFor('shift', 'store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['nama' => 'Hidayatmin', 'days' => ['1' => 'R']]]])
            ->assertSessionHasErrors('rows.0.days.1');
    }

    public function test_the_shift_sheet_counts_the_rekap_absensi(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post($this->routeFor('shift', 'store'), $period + ['rows' => [['pic' => 'OFFICER LOGISTIK', 'nama' => 'Hidayatmin', 'days' => ['3' => 'P', '4' => 'P', '5' => 'P', '6' => 'S', '1' => 'OF']]]])
            ->assertRedirect();

        $this->actingAs($user)
            ->get($this->routeFor('shift', 'index', $period))
            ->assertInertia(fn ($page) => $page->where('rows.0.summary', ['P' => 3, 'S' => 1, 'I' => 0, 'C' => 0, 'M' => 0, 'kehadiran' => '75%']));
    }

    public function test_the_ik_sheet_is_yearly(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);

        $this->actingAs($user)
            ->post($this->routeFor('ik', 'store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['nama' => 'IK Penerimaan Material', 'pic' => 'Officer', 'days' => ['2' => 'D', '9' => 'R']]]])
            ->assertRedirect();

        $this->assertSame(0, LogistikJadwalRow::query()->where('jadwal', 'ik')->value('month'));

        $this->actingAs($user)
            ->get($this->routeFor('ik', 'index', ['unit_id' => $unit->id, 'month' => 3, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->has('columns', 12)
                ->has('rows', LogistikJadwal::IK_ROWS)
                ->where('rows.0.nama', 'IK Penerimaan Material'),
            );
    }

    public function test_a_maturity_item_keeps_a_single_level(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post($this->routeFor('maturity', 'store'), $period + ['rows' => [['section' => 'd1', 'nama' => 'Terdapat data base cataloque material', 'days' => ['0' => 'L', '3' => 'L']]]])
            ->assertRedirect();

        $this->actingAs($user)
            ->get($this->routeFor('maturity', 'index', $period))
            ->assertInertia(fn ($page) => $page->where('rows.0.summary.level', 3));
    }

    public function test_checklist_eviden_photos_are_stored_and_capped(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);

        $this->actingAs($user)
            ->post($this->routeFor('inspeksi-5s5r', 'store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [[
                    'section' => 'ringkas',
                    'nama' => 'Alat rusak dipisahkan',
                    'days' => ['1' => 'D'],
                    'evidence' => ['logistik/lain/1/bukan-miliknya.jpg'],
                    'evidence_files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
                ]],
            ])
            ->assertRedirect();

        $row = LogistikJadwalRow::query()->where('jadwal', 'inspeksi-5s5r')->firstOrFail();
        $this->assertCount(2, $row->evidence);
        foreach ($row->evidence as $path) {
            $this->assertStringStartsWith("logistik/inspeksi-5s5r/{$unit->id}/", $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_a_role_without_logistik_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post($this->routeFor('meeting', 'store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }
}
