<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Http\Controllers\Operasi\FlmMonitoringController;
use App\Models\OperasiFlmMonitoring;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class FlmMonitoringInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_an_empty_month_starts_with_one_blank_row(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->get(route('operasi.input.flm-monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/flm-monitoring/index')
                ->has('rows', 1)
                ->where('rows.0.no_urut', 1)
                ->where('options.kondisi_awal', OperasiFlmMonitoring::KONDISI_AWAL)
                ->where('has_saved', false)
                ->where('can_write', true),
            );
    }

    public function test_findings_are_saved_renumbered_and_blank_rows_skipped(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.input.flm-monitoring.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [
                    ['mesin' => 'Fuel Transfer Pump', 'tanggal' => '2026-08-20', 'masalah' => 'Terdapat kebocoran BBM', 'kondisi_awal' => ['bersihkan', 'kencangkan', 'perbaikan_koneksi'], 'kondisi_akhir' => 'Ditadah', 'catatan' => 'Ditindaklanjuti oleh tim HAR', 'status' => 'close'],
                    ['mesin' => '', 'tanggal' => null, 'masalah' => '', 'kondisi_awal' => [], 'status' => 'open'],
                    ['mesin' => 'Muffler Cummins #6', 'tanggal' => '2026-08-28', 'masalah' => 'Sisi kanan miring', 'kondisi_awal' => ['perbaikan_koneksi'], 'kondisi_akhir' => 'Backlog', 'status' => 'open'],
                ],
            ])
            ->assertRedirect();

        $rows = OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->orderBy('no_urut')->get();
        $this->assertSame([1, 2], $rows->pluck('no_urut')->all());
        $this->assertSame(['bersihkan', 'kencangkan', 'perbaikan_koneksi'], $rows[0]->kondisi_awal);
        $this->assertSame('2026-08-28', $rows[1]->tanggal->toDateString());

        $this->actingAs($user)
            ->get(route('operasi.input.flm-monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('rows.0.mesin', 'Fuel Transfer Pump')->has('rows', 2));

        // A row deleted and saved stays gone: the page adds no blank rows back.
        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'rows' => [['mesin' => 'Muffler Cummins #6', 'tanggal' => '2026-08-28', 'masalah' => 'Sisi kanan miring', 'kondisi_awal' => [], 'status' => 'open']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->get(route('operasi.input.flm-monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.mesin', 'Muffler Cummins #6'));

        // Deleting every row empties the month.
        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->count());
    }

    public function test_the_kondisi_awal_and_akhir_photos_are_stored_kept_replaced_and_removed(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::Operator, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];
        $finding = ['mesin' => 'Fuel Transfer Pump', 'tanggal' => '2026-08-20', 'masalah' => 'Bocor BBM', 'status' => 'open'];

        // The operator (field input) uploads both photos.
        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), $period + ['rows' => [
            $finding + ['foto_awal_file' => UploadedFile::fake()->image('awal.jpg'), 'foto_akhir_file' => UploadedFile::fake()->image('akhir.jpg')],
        ]])->assertSessionHasNoErrors();

        $row = OperasiFlmMonitoring::query()->firstOrFail();
        $this->assertStringStartsWith("operasi-flm/{$unit->id}/", $row->foto_awal);
        $this->assertStringStartsWith("operasi-flm/{$unit->id}/", $row->foto_akhir);
        Storage::disk('public')->assertExists([$row->foto_awal, $row->foto_akhir]);
        [$awal, $akhir] = [$row->foto_awal, $row->foto_akhir];

        $this->actingAs($user)->get(route('operasi.input.flm-monitoring.index', $period))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.foto_awal', $awal)
                ->where('rows.0.foto_awal_url', fn (?string $url): bool => $url !== null && str_contains($url, $awal))
                ->has('rows', 1));

        // Saved again: the kept awal stays, the akhir is replaced (its old file deleted).
        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), $period + ['rows' => [
            $finding + ['foto_awal' => $awal, 'foto_akhir' => $akhir, 'foto_akhir_file' => UploadedFile::fake()->image('akhir-baru.jpg')],
        ]])->assertSessionHasNoErrors();

        $row = OperasiFlmMonitoring::query()->firstOrFail();
        $this->assertSame($awal, $row->foto_awal);
        $this->assertNotSame($akhir, $row->foto_akhir);
        Storage::disk('public')->assertExists([$awal, $row->foto_akhir]);
        Storage::disk('public')->assertMissing($akhir);

        // A path outside the unit's FLM folder is never taken over; removing a photo deletes it.
        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), $period + ['rows' => [
            $finding + ['foto_awal' => 'logistik/lain/1/bukan-miliknya.jpg', 'foto_akhir' => $row->foto_akhir],
        ]])->assertSessionHasNoErrors();

        $this->assertNull(OperasiFlmMonitoring::query()->firstOrFail()->foto_awal);
        Storage::disk('public')->assertMissing($awal);
    }

    public function test_a_finding_with_only_a_photo_is_kept_and_a_non_image_is_rejected(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), $period + ['rows' => [
            ['status' => 'open', 'foto_awal_file' => UploadedFile::fake()->image('awal.jpg')],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(1, OperasiFlmMonitoring::query()->count());

        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), $period + ['rows' => [
            ['mesin' => 'Pompa', 'status' => 'open', 'foto_akhir_file' => UploadedFile::fake()->create('catatan.pdf', 10, 'application/pdf')],
        ]])->assertSessionHasErrors('rows.0.foto_akhir_file');
    }

    public function test_the_photos_are_printed_in_the_pdf(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $path = UploadedFile::fake()->image('awal.png')->store("operasi-flm/{$unit->id}", 'public');
        OperasiFlmMonitoring::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'no_urut' => 1, 'mesin' => 'Pompa', 'foto_awal' => $path]);

        [$view, $data] = app(FlmMonitoringController::class)->pdfView($unit, 8, 2026);
        $html = view($view, $data)->render();

        $this->assertSame(['foto_awal' => 'Kondisi Awal', 'foto_akhir' => 'Kondisi Akhir'], $data['photos']);
        $this->assertStringContainsString('Eviden', $html);
        $this->assertStringContainsString('data:image/png;base64,', $data['rows'][0]['foto_awal_url']);
        $this->assertNull($data['rows'][0]['foto_akhir_url']);
    }

    public function test_an_unknown_kondisi_awal_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->post(route('operasi.input.flm-monitoring.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['mesin' => 'Pompa', 'kondisi_awal' => ['dicat'], 'status' => 'open']]])
            ->assertSessionHasErrors('rows.0.kondisi_awal.0');
    }

    public function test_the_pdf_is_landscape(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        OperasiFlmMonitoring::factory()->create(['unit_id' => $unit->id]);

        $response = $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->get(route('operasi.input.flm-monitoring.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_a_role_without_operasi_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->post(route('operasi.input.flm-monitoring.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }
}
