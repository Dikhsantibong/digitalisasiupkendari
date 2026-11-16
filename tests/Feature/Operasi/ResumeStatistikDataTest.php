<?php

namespace Tests\Feature\Operasi;

use App\Enums\FuelType;
use App\Enums\RoleName;
use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\OperasiDataTeknis;
use App\Models\OperasiInstruksiKerja;
use App\Models\OperasiPembuatanIk;
use App\Models\OperasiPerformanceTestMesin;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use App\Services\Operasi\OperasiReportTables;
use App\Services\Operasi\Reports\MonthlyEngineReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ResumeStatistikDataTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_resume_returns_zero_when_unit_has_no_saved_data(): void
    {
        $unit = Unit::factory()->create();

        $tables = app(OperasiReportTables::class)->build($unit, 11, 2026, 0);
        $rows = collect($tables['resume']['rows'])->keyBy('deskripsi');

        foreach ([
            'Jadwal First Line Maintenance (Rutin)',
            'Jadwal First Line Maintenance (Non Rutin)',
            'Jadwal 5S5R',
            'Jadwal Meeting Shift Operator',
            'Jadwal Inventarisasi Tools & Material Operasi',
            'Jadwal Instruksi Kerja (IK)',
            'Jadwal Pembuatan Data Teknis',
            'Jadwal Pemeriksaan Instalasi Blackstart',
            'Jadwal Pelaksanaan Performance Test Mesin Pembangkit',
        ] as $deskripsi) {
            $this->assertSame(0, $rows[$deskripsi]['target'], $deskripsi);
            $this->assertSame(0, $rows[$deskripsi]['realisasi'], $deskripsi);
        }

        $this->assertSame(30, $rows['Input Data Aplikasi Pembangkit']['target']);
        $this->assertSame(0, $rows['Input Data Aplikasi Pembangkit']['realisasi']);
    }

    public function test_resume_pulls_genuine_engine_days_ik_data_teknis_and_performance_test_realisasi(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create([
            'fuel_type' => FuelType::HsdOnly,
            'is_active' => true,
        ]);

        // Baseline previous-month stand + only 2 of 31 days in January 2026 have real daily engine inputs.
        DailyEngineReport::factory()->forEngineOnDate($engine, '2025-12-31')->create([
            'kwh_produksi_stand_akhir' => 1000,
            'kwh_pakai_sendiri_stand_akhir' => 100,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-01-01')->create([
            'kwh_produksi_stand_akhir' => 1120,
            'kwh_pakai_sendiri_stand_akhir' => 105,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-01-02')->create([
            'kwh_produksi_stand_akhir' => 1250,
            'kwh_pakai_sendiri_stand_akhir' => 110,
        ]);

        // IK: 3 planned in Jan, 2 genuinely uploaded in Jan (1 dated Jan, 1 undated created in Jan) + 1 in Feb (ignored).
        for ($i = 1; $i <= 3; $i++) {
            OperasiPembuatanIk::query()->create([
                'unit_id' => $unit->id,
                'year' => 2026,
                'no_urut' => $i,
                'nama' => "IK #{$i}",
                'months' => ['1' => '1'],
            ]);
        }
        OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id, 'tanggal' => '2026-01-10']);
        OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id, 'tanggal' => null, 'created_at' => Carbon::create(2026, 1, 15, 9, 0, 0)]);
        OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id, 'tanggal' => '2026-02-01']);

        // Data Teknis: 3 planned in Jan, only 1 marked realized in Jan.
        for ($i = 1; $i <= 3; $i++) {
            OperasiDataTeknis::query()->create([
                'unit_id' => $unit->id,
                'year' => 2026,
                'no_urut' => $i,
                'nama' => "Data Teknis #{$i}",
                'months' => ['1' => '1'],
                'realisasi' => $i === 1 ? ['1' => '1'] : [],
            ]);
        }

        // Performance Test: 4 machines scheduled in Jan, only 1 marked realized in Jan.
        for ($i = 1; $i <= 4; $i++) {
            OperasiPerformanceTestMesin::query()->create([
                'unit_id' => $unit->id,
                'year' => 2026,
                'no_urut' => $i,
                'nama_mesin' => "MESIN TEST #{$i}",
                'beban_50' => ['1-2'],
                'beban_75' => [],
                'beban_100' => [],
                'realisasi' => $i === 2 ? ['1-2'] : [],
            ]);
        }

        $monthly = app(MonthlyEngineReport::class)->build($unit, 1, 2026, null);
        $engineDaysFilled = collect($monthly['rows'])->filter(fn (array $row): bool => (bool) ($row['is_complete'] ?? false))->count();

        $this->assertSame(2, $engineDaysFilled);

        $tables = app(OperasiReportTables::class)->build($unit, 1, 2026, $engineDaysFilled);
        $rows = collect($tables['resume']['rows'])->keyBy('deskripsi');

        $this->assertSame([31, 2, '6%'], [
            $rows['Input Data Aplikasi Pembangkit']['target'],
            $rows['Input Data Aplikasi Pembangkit']['realisasi'],
            $rows['Input Data Aplikasi Pembangkit']['analisa'],
        ]);
        $this->assertSame([3, 2, '67%'], [
            $rows['Jadwal Instruksi Kerja (IK)']['target'],
            $rows['Jadwal Instruksi Kerja (IK)']['realisasi'],
            $rows['Jadwal Instruksi Kerja (IK)']['analisa'],
        ]);
        $this->assertSame([3, 1, '33%'], [
            $rows['Jadwal Pembuatan Data Teknis']['target'],
            $rows['Jadwal Pembuatan Data Teknis']['realisasi'],
            $rows['Jadwal Pembuatan Data Teknis']['analisa'],
        ]);
        $this->assertSame([4, 1, '25%'], [
            $rows['Jadwal Pelaksanaan Performance Test Mesin Pembangkit']['target'],
            $rows['Jadwal Pelaksanaan Performance Test Mesin Pembangkit']['realisasi'],
            $rows['Jadwal Pelaksanaan Performance Test Mesin Pembangkit']['analisa'],
        ]);
        $this->assertStringContainsString('MESIN TEST #1', $tables['tables']['performance_test']['body']);
    }

    public function test_controllers_persist_data_teknis_and_performance_test_realisasi_without_mirroring(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($user)->post(route('operasi.jadwal.pembuatan-data-teknis.store'), [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                ['nama' => 'Spec Mesin 1', 'pic' => 'Andi', 'months' => ['11' => '1'], 'realisasi' => []],
                ['nama' => 'Spec Mesin 2', 'pic' => 'Budi', 'months' => ['11' => '1'], 'realisasi' => ['11' => '1']],
            ],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('operasi.jadwal.performance-test.store'), [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                ['nama_mesin' => 'MESIN #01', 'beban_50' => ['11-1'], 'beban_75' => [], 'beban_100' => [], 'realisasi' => ['11-1']],
                ['nama_mesin' => 'MESIN #02', 'beban_50' => ['11-2'], 'beban_75' => [], 'beban_100' => [], 'realisasi' => []],
            ],
        ])->assertRedirect();

        $this->assertCount(2, OperasiDataTeknis::query()->where('unit_id', $unit->id)->get());
        $this->assertSame([], OperasiDataTeknis::query()->where('nama', 'Spec Mesin 1')->firstOrFail()->realisasi);
        $this->assertSame(['11' => '1'], OperasiDataTeknis::query()->where('nama', 'Spec Mesin 2')->firstOrFail()->realisasi);

        $this->assertSame(['11-1'], OperasiPerformanceTestMesin::query()->where('nama_mesin', 'MESIN #01')->firstOrFail()->realisasi);
        $this->assertSame([], OperasiPerformanceTestMesin::query()->where('nama_mesin', 'MESIN #02')->firstOrFail()->realisasi);
    }

    public function test_stale_cached_document_rebuilds_when_opened_after_body_version_bump(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        OperasiDataTeknis::query()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'nama' => 'Data Teknis Boiler',
            'months' => ['11' => '1'],
            'realisasi' => [],
        ]);

        OperasiReportDocument::query()->create([
            'unit_id' => $unit->id,
            'report_code' => 'laporan-operasi-bulanan',
            'year' => 2026,
            'month' => 11,
            'document_number' => 'LAP-OPS/TEST/11-2026',
            'format' => 'html',
            'content_version' => 8,
            'content_html' => '<div id="stale-cache">STALE</div>',
            'created_by' => $user->id,
        ]);

        $url = route('operasi.laporan.document.edit', [
            'report' => 'laporan-operasi-bulanan',
            'unit_id' => $unit->id,
            'month' => 11,
            'year' => 2026,
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', false)
                ->loadDeferredProps('document', fn ($reload) => $reload
                    ->where('content', fn (string $html): bool => ! str_contains($html, 'STALE') && str_contains($html, 'RESUME STATISTIK OPERASI'))
                )
            );
    }
}
