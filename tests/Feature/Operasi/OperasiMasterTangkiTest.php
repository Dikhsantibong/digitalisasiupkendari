<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\Unit;
use App\Services\Operasi\UnitFuelTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Tangki BBM (Data Master Operasi): saving with empty optional numbers, and
 * the tank's Jenis BBM driving the unit's BBM list.
 */
class OperasiMasterTangkiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_a_tank_is_saved_with_an_empty_urutan_and_its_jenis_bbm_becomes_a_bbm_of_the_unit(): void
    {
        $this->seedAccessControl();
        $unit = Unit::factory()->create();
        $b30 = BbmType::factory()->create(['code' => 'B30', 'name' => 'Biosolar B30', 'material_code' => '8010009']);
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($admin)->post(route('operasi.master.store', 'fuel-tanks'), [
            'unit_id' => $unit->id,
            'code' => 'tangki',
            'name' => 'Tangki 1',
            'bbm_type_id' => $b30->id,
            'fuel_type' => 'hsd',
            'capacity_liter' => 1000,
            'is_daily_tank' => 1,
            'sort_order' => null,
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tank = FuelTank::query()->sole();
        $this->assertSame(0, (int) $tank->sort_order);
        $this->assertSame($b30->id, $tank->bbm_type_id);

        // Updating with an empty urutan keeps the stored one.
        $tank->update(['sort_order' => 3]);
        $this->actingAs($admin)->put(route('operasi.master.update', ['fuel-tanks', $tank->id]), [
            'unit_id' => $unit->id, 'name' => 'Tangki 1', 'bbm_type_id' => $b30->id, 'fuel_type' => 'hsd', 'sort_order' => '', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, (int) $tank->fresh()->sort_order);

        $this->assertSame([['code' => 'B30', 'name' => 'Biosolar B30', 'material_code' => '8010009']], array_map(
            fn (array $fuel): array => ['code' => $fuel['code'], 'name' => $fuel['name'], 'material_code' => $fuel['material_code']],
            app(UnitFuelTypes::class)->forUnit($unit),
        ));
    }
}
