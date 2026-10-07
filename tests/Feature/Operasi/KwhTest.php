<?php

namespace Tests\Feature\Operasi;

use App\Enums\CalibrationFactorType;
use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\BbmType;
use App\Models\CalibrationFactor;
use App\Models\DailyEngineReport;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Operasi\OperasiCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * kWh section of Pengusahaan Operasi: Stand kWh Harian is the input; Transfer
 * Pricing, Energi Dibangkit and Energi Pemakaian Sendiri are read from it.
 */
class KwhTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak2;

    private Machine $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Wua-Wua']);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'merk' => 'MAK', 'type' => '8M 453 AK', 'serial_number' => '26881', 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #2', 'merk' => 'MAK', 'is_active' => true]);
        $this->cat = Machine::factory()->forUnit($this->unit)->create(['name' => 'ZCAT #8', 'merk' => 'Caterpillar', 'kwh_faktor_kali_ps' => 160, 'is_active' => true]);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    private function fill(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->post(route('operasi.pengusahaan.stand-kwh.store'), $this->period([
            'opening' => [
                $this->mak1->id => ['produksi' => 201505065, 'ps' => 8515376],
                $this->mak2->id => ['produksi' => 188778551, 'ps' => 8429748],
                $this->cat->id => ['produksi' => 22349.9, 'ps' => 28622.1],
            ],
            'stands' => [
                $this->mak1->id => ['1' => ['produksi' => 201524595, 'ps' => 8516651], '2' => ['produksi' => 201543585, 'ps' => 8517915]],
                $this->mak2->id => ['1' => ['produksi' => 188803851, 'ps' => 8430911], '29' => ['produksi' => null, 'ps' => null]],
                $this->cat->id => ['1' => ['produksi' => 22349.9, 'ps' => 28622.2]],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_stand_kwh_harian_carries_the_stands_and_computes_produksi_ps_and_nett(): void
    {
        $this->fill();

        // The opening stands land on the last day of the previous month; empty days are not created.
        $this->assertSame(201505065.0, (float) DailyEngineReport::query()->where('engine_id', $this->mak1->id)->whereDate('report_date', '2026-08-31')->value('kwh_produksi_stand_akhir'));
        $this->assertSame(0, DailyEngineReport::query()->where('engine_id', $this->mak2->id)->whereDate('report_date', '2026-09-29')->count());

        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->get(route('operasi.pengusahaan.stand-kwh.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/stand-kwh/index')
                ->has('sheets', 3)
                ->where('sheets.0.machine.name', 'MAK #1')
                ->where('sheets.0.has_previous', true)
                ->where('sheets.0.stand_awal_produksi', 201505065)
                // Day 1 MAK #1: produksi 19.530, PS 1.275, nett 18.255 (Excel).
                ->where('sheets.0.rows.0.produksi', 19530)
                ->where('sheets.0.rows.0.ps', 1275)
                ->where('sheets.0.rows.0.nett', 18255)
                ->where('sheets.0.rows.1.produksi_awal', 201524595)
                ->where('sheets.0.rows.1.produksi', 18990)
                ->where('sheets.0.rows.1.nett', 17726)
                ->where('sheets.0.rows.2.produksi', null)
                ->where('sheets.0.totals.nett', 35981)
                ->where('sheets.0.stand_akhir_produksi', 201543585)
                // The PS meter of the Caterpillar has faktor kali 160: 0,1 × 160 = 16 kWh.
                ->where('sheets.2.faktor_kali_ps', 160)
                ->where('sheets.2.rows.0.ps', 16)
                ->where('sheets.2.rows.0.nett', -16)
                ->where('can_write', true));
    }

    public function test_the_laporan_calculator_reads_the_same_stands(): void
    {
        $this->fill();

        $grid = app(OperasiCalculator::class)->buildDailyEngineGrid($this->cat->fresh(), 9, 2026);

        $this->assertEqualsWithDelta(16.0, $grid['rows'][0]['kwh_pakai_sendiri'], 0.001);
    }

    public function test_transfer_pricing_lists_the_month_stands_per_meter(): void
    {
        $this->fill();
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kwh-tp.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/kwh-tp/index')
                ->where('produksi.0.merk', 'MAK')
                ->where('produksi.0.stand_awal', 201505065)
                ->where('produksi.0.stand_akhir', 201543585)
                ->where('produksi.0.hasil', 38520)
                ->where('produksi.1.hasil', 25300)
                ->where('ps.2.faktor_kali', 160)
                ->where('total_produksi', 63820)
                ->where('total_ps', 3718));
    }

    public function test_transfer_pricing_hasil_is_stand_akhir_minus_stand_awal_without_kalibrasi(): void
    {
        $this->fill();
        CalibrationFactor::factory()->create([
            'unit_id' => $this->unit->id,
            'factor_type' => CalibrationFactorType::Kwh,
            'value' => 1.5,
            'effective_date' => '2026-01-01',
        ]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kwh-tp.index', $this->period()))
            ->assertInertia(fn ($page) => $page
                // 201543585 − 201505065, faktor kali 1.
                ->where('produksi.0.hasil', 38520)
                // (28622.2 − 28622.1) × faktor kali 160.
                ->where('ps.2.hasil', 16));
    }

    public function test_kwh_rekap_shows_kwh_ps_and_netto_from_stand_kwh_harian(): void
    {
        $this->fill();
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kwh-rekap.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/kwh-rekap/index')
                ->where('groups.0.merk', 'MAK')
                ->has('groups.0.machines', 2)
                ->where('blocks.0.key', 'produksi')
                ->where('blocks.1.key', 'ps')
                ->where('blocks.2.key', 'nett')
                // 201524595 − 201505065, 8516651 − 8515376 and their difference.
                ->where("blocks.0.values.{$this->mak1->id}.1", 19530)
                ->where("blocks.1.values.{$this->mak1->id}.1", 1275)
                ->where("blocks.2.values.{$this->mak1->id}.1", 18255)
                ->where('blocks.2.grand_total', fn ($total) => abs($total - (63820 - 3718)) < 0.01));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kwh-rekap.pdf', $this->period()))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_sfc_and_sfc_netto_divide_every_jenis_bbm_by_kwh(): void
    {
        $this->fill();
        $hsd = BbmType::factory()->create(['code' => 'HSD', 'name' => 'Bio Solar', 'sort_order' => 0]);
        $mfo = BbmType::factory()->create(['code' => 'MFO', 'name' => 'MFO', 'sort_order' => 1]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $hsd->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $mfo->id, 'fuel_type' => TankFuelType::Mfo, 'is_active' => true]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period(['readings' => [
            "HSD_{$this->mak1->id}" => ['1' => 5000],
            "MFO_{$this->mak1->id}" => ['1' => 418],
        ]]))->assertSessionHasNoErrors();

        // Day 1 of MAK #1: kWh 19530, kWh netto 18255; day 2 has kWh but no BBM.
        $this->actingAs($tl)->get(route('operasi.pengusahaan.sfc.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/sfc/index')
                ->has('fuels', 2)
                ->where("values.{$this->mak1->id}.1", round(5418 / 19530, 4))
                ->where("values.{$this->mak1->id}.2", 0)
                ->where("totals_by_machine.{$this->mak1->id}", round(5418 / (19530 + 18990), 4)));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.sfc-netto.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/sfc-netto/index')
                ->where("values.{$this->mak1->id}.1", round(5418 / 18255, 4))
                // ZCAT #8 has no kWh netto: no division.
                ->where("values.{$this->cat->id}.1", 0));

        foreach (['sfc', 'sfc-netto'] as $menu) {
            $this->actingAs($tl)->get(route("operasi.pengusahaan.{$menu}.pdf", $this->period()))
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_energi_sheets_group_the_machines_by_merk(): void
    {
        $this->fill();
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.energi-dibangkit.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/energi-dibangkit/index')
                ->has('groups', 2)
                ->where('groups.0.merk', 'MAK')
                ->has('groups.0.machines', 2)
                ->where('groups.0.totals_by_day.1', 18255 + 24137)
                ->where('groups.1.merk', 'CATERPILLAR')
                ->where('totals_by_day.1', 18255 + 24137 - 16)
                ->where('grand_total', 35981 + 24137 - 16));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.energi-pemakaian-sendiri.index', $this->period()))
            ->assertInertia(fn ($page) => $page->where("values.{$this->mak1->id}.1", 1275)->where('grand_total', 1275 + 1264 + 1163 + 16));
    }

    public function test_readers_view_and_print_but_cannot_save(): void
    {
        $this->fill();
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.stand-kwh.index', $this->period()))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.stand-kwh.store'), $this->period(['stands' => []]))->assertForbidden();

        foreach (['stand-kwh', 'kwh-tp', 'energi-dibangkit', 'energi-pemakaian-sendiri'] as $menu) {
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.{$menu}.pdf", $this->period()))
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        $other = $this->userWithRole(RoleName::TeamLeaderOperasi, Unit::factory()->create());
        $this->actingAs($other)->get(route('operasi.pengusahaan.kwh-tp.pdf', $this->period()))->assertForbidden();
    }
}
