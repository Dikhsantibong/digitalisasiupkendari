<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Http\Controllers\Operasi\FlmMonitoringController;
use App\Models\OperasiFlmMonitoring;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_the_page_shows_the_numbered_blank_rows(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.input.flm-monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/flm-monitoring/index')
                ->has('rows', FlmMonitoringController::MIN_ROWS)
                ->where('rows.9.no_urut', 10)
                ->where('options.kondisi_awal', OperasiFlmMonitoring::KONDISI_AWAL)
                ->where('has_saved', false)
                ->where('can_write', true),
            );
    }

    public function test_findings_are_saved_renumbered_and_blank_rows_skipped(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

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
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('rows.0.mesin', 'Fuel Transfer Pump')->has('rows', FlmMonitoringController::MIN_ROWS));
    }

    public function test_an_unknown_kondisi_awal_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.input.flm-monitoring.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['mesin' => 'Pompa', 'kondisi_awal' => ['dicat'], 'status' => 'open']]])
            ->assertSessionHasErrors('rows.0.kondisi_awal.0');
    }

    public function test_the_pdf_is_landscape(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        OperasiFlmMonitoring::factory()->create(['unit_id' => $unit->id]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.input.flm-monitoring.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_a_role_without_operasi_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->post(route('operasi.input.flm-monitoring.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }
}
