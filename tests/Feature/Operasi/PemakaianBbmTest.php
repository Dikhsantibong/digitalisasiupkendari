<?php

namespace Tests\Feature\Operasi;

use App\Enums\FuelType;
use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Enums\TugJenis;
use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\OperasiPemakaianBbm;
use App\Models\OperasiStandMeter;
use App\Models\OperasiTug;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Pemakaian Bahan Bakar (per jenis BBM × mesin × tanggal) and the TUG 9 BBM
 * built from it.
 */
class PemakaianBbmTest extends TestCase
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
        $hsd = BbmType::factory()->create(['code' => 'HSD', 'name' => 'Bio Solar/HSD', 'material_code' => '8010001', 'sort_order' => 0]);
        $mfo = BbmType::factory()->create(['code' => 'MFO', 'name' => 'MFO', 'material_code' => '8010031', 'sort_order' => 1]);
        // The unit's jenis BBM come from its Tangki BBM (master): HSD and MFO here.
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $hsd->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $mfo->id, 'fuel_type' => TankFuelType::Mfo, 'is_active' => true]);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'type' => 'SM 453 AK', 'serial_number' => '26881', 'fuel_type' => FuelType::HsdMfo, 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #2', 'serial_number' => '26882', 'fuel_type' => FuelType::HsdOnly, 'is_active' => true]);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    public function test_the_blocks_follow_the_bbm_of_the_unit_and_its_machines(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.pemakaian-bbm.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/pemakaian-bbm/index')
                ->has('fuels', 2)
                ->where('fuels.0.code', 'HSD')
                ->where('fuels.0.name', 'Bio Solar/HSD')
                ->has('fuels.0.machines', 2)
                ->where('fuels.0.machines.0.type', 'SM 453 AK')
                ->where('fuels.1.code', 'MFO')
                ->has('fuels.1.machines', 2)
                ->where('days_in_month', 30)
                ->where('can_write', true));
    }

    public function test_saving_keeps_valid_cells_and_computes_the_totals(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period([
            'readings' => [
                "HSD_{$this->mak1->id}" => ['1' => 5418, '2' => 5270.5, '31' => 99],
                "HSD_{$this->mak2->id}" => ['1' => 7007],
                // B30 is not a BBM of this unit.
                "MFO_{$this->mak2->id}" => ['1' => 10],
                "B30_{$this->mak1->id}" => ['1' => 10],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $record = OperasiPemakaianBbm::query()->sole();
        $this->assertSame(["HSD_{$this->mak1->id}", "HSD_{$this->mak2->id}", "MFO_{$this->mak2->id}"], array_keys($record->raw_readings));
        $this->assertEquals(17695.5, $record->totals_by_fuel['HSD']);
        $this->assertEquals(10, $record->totals_by_fuel['MFO']);
        $this->assertSame('17705.50', $record->grand_total_liter);

        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period(['readings' => ["HSD_{$this->mak1->id}" => ['1' => -1]]]))
            ->assertSessionHasErrors('readings.*.*');
    }

    public function test_the_stand_meter_usage_is_offered_as_a_starting_point(): void
    {
        OperasiStandMeter::query()->create([
            'unit_id' => $this->unit->id,
            'fuel_name' => 'HSD',
            'month' => 9,
            'year' => 2026,
            'readings' => [
                ['tgl' => 1, 'machines' => [$this->mak1->id => ['machine_id' => $this->mak1->id, 'awal' => 1, 'akhir' => 2, 'pemakaian' => 5418], $this->mak2->id => ['machine_id' => $this->mak2->id, 'pemakaian' => 0]]],
                ['tgl' => 2, 'machines' => [$this->mak2->id => ['machine_id' => $this->mak2->id, 'pemakaian' => 7329]]],
            ],
        ]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.pemakaian-bbm.index', $this->period()))
            ->assertInertia(fn ($page) => $page
                ->where("stand_meter.HSD_{$this->mak1->id}.1", 5418)
                ->where("stand_meter.HSD_{$this->mak2->id}.2", 7329)
                ->missing("stand_meter.HSD_{$this->mak2->id}.1"));
    }

    public function test_readers_view_and_print_but_cannot_save(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.pemakaian-bbm.index', $this->period()))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.pemakaian-bbm.pdf', $this->period()))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period(['readings' => []]))->assertForbidden();
    }

    public function test_tug_bbm_is_built_per_machine_from_pemakaian_bahan_bakar(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period([
            'readings' => [
                "HSD_{$this->mak1->id}" => ['1' => 5418, '2' => 5270],
                "MFO_{$this->mak1->id}" => ['2' => 100],
                "HSD_{$this->mak2->id}" => ['1' => 7007],
            ],
        ]));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-bbm.index', $this->period(['machine_id' => $this->mak1->id])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/tug-bbm/index')
                ->where('machines.0.total', 10788)
                ->where('machines.1.total', 7007)
                ->where('tug.jenis', 'bbm')
                ->has('tug.columns', 2)
                ->where('tug.columns.0.name', 'BBM Bio Solar/HSD')
                ->where('tug.columns.0.code', '8010001')
                ->where('tug.columns.1.code', '8010031')
                ->where('tug.days.1.values.HSD', 5270)
                ->where('tug.days.1.total', 5370)
                ->where('tug.totals.HSD', 10688)
                ->where('tug.grand_total', 10788));

        // Every machine has a column per jenis BBM of the unit.
        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-bbm.index', $this->period(['machine_id' => $this->mak2->id])))
            ->assertInertia(fn ($page) => $page->has('tug.columns', 2)->where('tug.grand_total', 7007));

        // The header is kept apart from the TUG Pelumas header of the same machine.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.tug-bbm.store'), $this->period(['machine_id' => $this->mak1->id, 'nomor' => '00086/LOG.00.02/560231/2026', 'pekerjaan' => 'RUTIN', 'cost_center' => '2503133101']))
            ->assertSessionHasNoErrors();
        $this->actingAs($tl)->post(route('operasi.pengusahaan.tug-pelumas.store'), $this->period(['machine_id' => $this->mak1->id, 'nomor' => '00081/LOG.00.02/550231/2026', 'pekerjaan' => 'RUTIN']))
            ->assertSessionHasNoErrors();
        $this->assertSame('00086/LOG.00.02/560231/2026', OperasiTug::query()->where('jenis', TugJenis::Bbm)->sole()->nomor);
        $this->assertSame('00081/LOG.00.02/550231/2026', OperasiTug::query()->where('jenis', TugJenis::Pelumas)->sole()->nomor);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-bbm.pdf', $this->period(['all' => 1])))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
