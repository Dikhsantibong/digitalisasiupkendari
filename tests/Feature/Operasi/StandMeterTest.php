<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\BbmType;
use App\Models\DailyEngineReport;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class StandMeterTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_loading_stand_meter_page_displays_active_machines_and_defaults(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Wua-Wua']);
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $machine = Machine::factory()->forUnit($unit)->create([
            'name' => 'MAK #2',
            'is_active' => true,
        ]);

        DailyEngineReport::factory()->forEngineOnDate($machine, '2026-07-31')->create([
            'flowmeter_hsd_stand_akhir' => 15000.75,
        ]);

        $response = $this->actingAs($user)
            ->get(route('operasi.pengusahaan.stand-meter.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'fuel' => 'HSD',
            ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/stand-meter/index')
                ->where('fuel_name', 'HSD')
                ->has('machine_parameters', 1)
                ->where('machine_parameters.0.stand_awal_bln_lalu', 15000.75)
                ->has('readings', 31)
            );
    }

    public function test_saving_stand_meter_persists_to_database(): void
    {
        $unit = Unit::factory()->create();
        $machine = Machine::factory()->forUnit($unit)->create(['name' => 'MAK #4', 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $unit->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $machineParameters = [
            [
                'id' => $machine->id,
                'name' => 'MAK #4',
                'stand_awal_bln_lalu' => 5000,
                'faktor_koreksi' => 0.9995,
                'faktor_kali' => 1,
            ],
        ];

        $readings = [
            [
                'tgl' => 1,
                'machines' => [
                    $machine->id => [
                        'machine_id' => $machine->id,
                        'awal' => 5000,
                        'akhir' => 5250,
                        'pemakaian' => 249.88,
                    ],
                ],
                'adm' => 249.88,
                'real' => 250,
                'selisih' => 0.12,
            ],
        ];

        $totals = [
            'machines' => [
                $machine->id => ['awal' => 5000, 'akhir' => 5250, 'pemakaian' => 249.88],
            ],
            'adm' => 249.88,
            'real' => 250,
            'selisih' => 0.12,
        ];

        $response = $this->actingAs($user)
            ->post(route('operasi.pengusahaan.stand-meter.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'fuel_name' => 'HSD',
                'machine_parameters' => $machineParameters,
                'readings' => $readings,
                'totals' => $totals,
                'catatan' => 'Uji coba stand meter',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('operasi_stand_meters', [
            'unit_id' => $unit->id,
            'fuel_name' => 'HSD',
            'month' => 8,
            'year' => 2026,
            'catatan' => 'Uji coba stand meter',
        ]);
    }

    public function test_pdf_export_for_stand_meter(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $response = $this->actingAs($user)
            ->get(route('operasi.pengusahaan.stand-meter.pdf', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'fuel' => 'HSD',
            ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_the_jenis_bbm_follow_the_master_of_each_unit(): void
    {
        // The jenis BBM of a unit are its tanks' Jenis BBM (master), not fixed in code.
        $hsd = BbmType::factory()->create(['code' => 'HSD', 'name' => 'High Speed Diesel', 'sort_order' => 0]);
        $mfo = BbmType::factory()->create(['code' => 'MFO', 'name' => 'Marine Fuel Oil 180', 'sort_order' => 1]);
        $poasia = Unit::factory()->create();
        FuelTank::factory()->create(['unit_id' => $poasia->id, 'bbm_type_id' => $mfo->id, 'fuel_type' => TankFuelType::Mfo, 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $poasia->id, 'bbm_type_id' => $hsd->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);

        $wuaWua = Unit::factory()->create();
        FuelTank::factory()->create(['unit_id' => $wuaWua->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $wuaWua->id, 'fuel_type' => TankFuelType::Mfo, 'is_active' => false]);

        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $poasia);

        $this->actingAs($user)->get(route('operasi.pengusahaan.stand-meter.index', ['unit_id' => $poasia->id, 'month' => 8, 'year' => 2026, 'fuel' => 'MFO']))
            ->assertInertia(fn ($page) => $page
                ->where('available_fuels', [['code' => 'HSD', 'name' => 'High Speed Diesel'], ['code' => 'MFO', 'name' => 'Marine Fuel Oil 180']])
                ->where('fuel_name', 'MFO'));

        $wuaWuaUser = $this->userWithRole(RoleName::TeamLeaderOperasi, $wuaWua);
        $this->actingAs($wuaWuaUser)->get(route('operasi.pengusahaan.stand-meter.index', ['unit_id' => $wuaWua->id, 'fuel' => 'MFO']))
            ->assertInertia(fn ($page) => $page->where('available_fuels', [['code' => 'HSD', 'name' => 'High Speed Diesel']])->where('fuel_name', 'HSD'));

        // A BBM the unit does not use cannot be saved.
        $this->actingAs($wuaWuaUser)->post(route('operasi.pengusahaan.stand-meter.store'), ['unit_id' => $wuaWua->id, 'month' => 8, 'year' => 2026, 'fuel_name' => 'MFO'])
            ->assertSessionHasErrors('fuel_name');
        $this->assertDatabaseCount('operasi_stand_meters', 0);
    }

    public function test_a_unit_without_jenis_bbm_in_master_cannot_be_filled(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)->get(route('operasi.pengusahaan.stand-meter.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('available_fuels', [])->where('can_manage', false));
    }

    public function test_readers_cannot_save(): void
    {
        $unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create();
        FuelTank::factory()->create(['unit_id' => $unit->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $unit->serviceUnit);

        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.stand-meter.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_manage', false));
        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.stand-meter.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'fuel_name' => 'HSD'])
            ->assertForbidden();
    }
}
