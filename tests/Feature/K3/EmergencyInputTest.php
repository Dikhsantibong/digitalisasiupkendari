<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\EmergencyEquipment;
use App\Models\EmergencyFacilityCheck;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class EmergencyInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_k3_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.input.emergency.index'))
            ->assertForbidden();
    }

    public function test_tl_sees_a_row_per_equipment_with_computed_readiness(): void
    {
        $unit = Unit::factory()->create();
        $equipment = EmergencyEquipment::factory()->forUnit($unit)->create();
        EmergencyFacilityCheck::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'week' => null,
            'emergency_equipment_id' => $equipment->id, 'jml_total' => 4, 'jml_ready' => 3, 'jml_not_ready' => 1,
        ]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.input.emergency.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/emergency')
                ->where('filters.week', 'bulanan')
                ->has('rows', 1)
                ->where('rows.0.persen_kesiapan', '75%'),
            );
    }

    public function test_saving_stores_monthly_and_weekly_separately(): void
    {
        $unit = Unit::factory()->create();
        $equipment = EmergencyEquipment::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);
        $base = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['equipment_id' => $equipment->id, 'jml_total' => 2, 'jml_ready' => 2, 'jml_not_ready' => 0]]];

        $this->actingAs($user)->post(route('k3.input.emergency.store'), [...$base, 'week' => 'bulanan'])->assertRedirect();
        $this->actingAs($user)->post(route('k3.input.emergency.store'), [...$base, 'week' => '2'])->assertRedirect();

        $this->assertSame(2, EmergencyFacilityCheck::query()->where('unit_id', $unit->id)->count());
        $this->assertDatabaseHas('emergency_facility_checks', ['emergency_equipment_id' => $equipment->id, 'week' => null]);
        $this->assertDatabaseHas('emergency_facility_checks', ['emergency_equipment_id' => $equipment->id, 'week' => 2]);
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.emergency.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'week' => 'bulanan', 'rows' => [],
            ])
            ->assertForbidden();
    }
}
