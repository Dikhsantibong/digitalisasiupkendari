<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\OperasiRekap;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Daftar Inventarisasi Mesin and Data Kinerja Pembangkit Termal, both computed
 * from the other Pengusahaan Operasi sheets and correctable.
 */
class RekapPengusahaanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Wua-Wua']);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create([
            'name' => 'WUA-WUA 1', 'merk' => 'MAK', 'type' => '8M 453 AK', 'serial_number' => '26881', 'capacity_kw' => 2544, 'is_active' => true,
            'engine_hp' => '3600', 'engine_rpm' => 600, 'tahun_pembuatan' => 1986,
            'generator_merk' => 'SIEMENS', 'generator_type' => '1FC 7809 3 HA63Z', 'generator_serial_number' => 'D-8662606201', 'generator_volt' => 6300, 'generator_kva' => 3180, 'generator_cos_phi' => 0.8,
        ]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'WUA-WUA 2', 'merk' => 'MAK', 'capacity_kw' => 2544, 'is_active' => true]);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    public function test_inventaris_shows_the_master_with_mampu_and_beban_tertinggi(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.beban-tinggi.store'), $this->period([
            'readings' => [$this->mak1->id => ['1' => 1500, '2' => 1400], $this->mak2->id => ['1' => 1450]],
            'daya_mampu' => [$this->mak1->id => 1700, $this->mak2->id => 1600],
        ]));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.inventaris-mesin.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/inventaris-mesin/index')
                ->where('rows.0.machine.generator_volt', 6300)
                ->where('rows.0.machine.generator_kva', 3180)
                ->where('rows.0.machine.generator_cos_phi', 0.8)
                ->where('rows.0.machine.engine_rpm', 600)
                ->where('rows.0.mampu', 1700)
                ->where('rows.0.beban', 1500)
                ->where('totals.terpasang', 5088)
                ->where('totals.beban', 2950));

        $this->actingAs($tl)->post(route('operasi.pengusahaan.inventaris-mesin.store'), $this->period([
            'values' => [$this->mak1->id => ['mampu' => '1700', 'beban' => '1500', 'ket' => ''], $this->mak2->id => ['mampu' => '1000', 'beban' => '1450', 'ket' => 'Gangg. C. Shaft']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([$this->mak2->id => ['mampu' => 1000, 'ket' => 'Gangg. C. Shaft']], OperasiRekap::query()->where('jenis', 'inventaris')->sole()->overrides);
        $this->actingAs($tl)->get(route('operasi.pengusahaan.inventaris-mesin.pdf', $this->period()))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_kinerja_termal_applies_the_formulas(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        // 30 days of September: jam periode 720.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-operasi.store'), $this->period(['readings' => [$this->mak1->id => ['1' => 24, '2' => 24, '3' => 12], $this->mak2->id => ['1' => 20]]]));
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-pemeliharaan.store'), $this->period(['readings' => [$this->mak1->id => ['4' => 7.2]]]));
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-gangguan.store'), $this->period(['readings' => [$this->mak2->id => ['2' => 5]]]));
        $this->actingAs($tl)->post(route('operasi.pengusahaan.kali-gangguan.store'), $this->period(['readings' => [$this->mak2->id => ['2' => 2]]]));
        DailyEngineReport::query()->create(['unit_id' => $this->unit->id, 'engine_id' => $this->mak1->id, 'report_date' => '2026-08-31', 'kwh_produksi_stand_akhir' => 1000]);
        DailyEngineReport::query()->create(['unit_id' => $this->unit->id, 'engine_id' => $this->mak1->id, 'report_date' => '2026-09-03', 'kwh_produksi_stand_akhir' => 92584]);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period());

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kinerja-termal.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/kinerja-termal/index')
                ->where('jam_periode', 720)
                // MAK 1: 60 jam operasi, 7,2 jam HAR, 91.584 kWh, 2.544 kW.
                ->where('rows.0.values.sf', 8.33)
                ->where('rows.0.values.pof', 1)
                ->where('rows.0.values.sof', 1)
                ->where('rows.0.values.eaf', 99)
                ->where('rows.0.values.cf', 5)
                ->where('rows.0.values.of', 60)
                // MAK 2: 20 jam operasi, 5 jam gangguan, 2 kali.
                ->where('rows.1.values.for', 20)
                ->where('rows.1.values.efor', 20)
                ->where('rows.1.values.fof', 0.69)
                ->where('rows.1.values.sdof', 2)
                ->where('averages.eaf', round((99 + (100 - 0.69)) / 2, 2)));

        $this->actingAs($tl)->post(route('operasi.pengusahaan.kinerja-termal.store'), $this->period(['values' => [$this->mak2->id => ['efor' => '3.95', 'for' => '20']]]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->mak2->id => ['efor' => 3.95]], OperasiRekap::query()->where('jenis', 'kinerja-termal')->sole()->overrides);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kinerja-termal.pdf', $this->period()))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_readers_cannot_correct(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        foreach (['inventaris-mesin', 'kinerja-termal'] as $menu) {
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.{$menu}.index", $this->period()))->assertOk()
                ->assertInertia(fn ($page) => $page->where('can_write', false));
            $this->actingAs($managerUl)->post(route("operasi.pengusahaan.{$menu}.store"), $this->period(['values' => []]))->assertForbidden();
        }
    }
}
