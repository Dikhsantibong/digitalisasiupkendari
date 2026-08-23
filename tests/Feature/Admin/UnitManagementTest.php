<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\ActivityLog;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_guests_are_redirected_away_from_the_unit_list(): void
    {
        $this->get(route('admin.units.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_permission_cannot_open_the_unit_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.units.index'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_every_unit(): void
    {
        Unit::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/units/index')
                ->has('units.data', 3),
            );
    }

    public function test_a_manager_only_sees_units_within_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        Unit::factory()->count(2)->forServiceUnit($serviceUnit)->create();
        Unit::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('units.data', 2));
    }

    public function test_an_operator_cannot_open_a_unit_outside_their_scope(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();

        $operator = $this->userWithRole(RoleName::Operator, $ownUnit);

        $this->actingAs($operator)
            ->get(route('admin.units.show', $ownUnit))
            ->assertOk();

        $this->actingAs($operator)
            ->get(route('admin.units.show', $otherUnit))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_a_unit_and_the_action_is_logged(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($superAdmin)
            ->post(route('admin.units.store'), [
                'service_unit_id' => $serviceUnit->id,
                'code' => 'pltd-baru',
                'name' => 'PLTD Baru',
                'type' => UnitType::Pltd->value,
                'status' => UnitStatus::Operating->value,
                'installed_capacity_mw' => '12.5',
                'location' => 'Kendari',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.units.index'));

        $unit = Unit::query()->where('code', 'PLTD-BARU')->firstOrFail();

        $this->assertSame('pltd-baru', $unit->slug);
        $this->assertSame($serviceUnit->id, $unit->service_unit_id);
        $this->assertSame(UnitType::Pltd, $unit->type);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superAdmin->id,
            'event' => ActivityEvent::Created->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_creating_a_unit_requires_a_unique_code(): void
    {
        $existing = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.units.store'), [
                'code' => $existing->code,
                'name' => 'PLTD Duplikat',
                'type' => UnitType::Pltd->value,
                'status' => UnitStatus::Operating->value,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_an_operator_cannot_create_a_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('admin.units.store'), [
                'code' => 'PLTD-X',
                'name' => 'PLTD X',
                'type' => UnitType::Pltd->value,
                'status' => UnitStatus::Operating->value,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('units', ['code' => 'PLTD-X']);
    }

    public function test_a_manager_cannot_update_a_unit_outside_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->put(route('admin.units.update', $foreignUnit), [
                'code' => $foreignUnit->code,
                'name' => 'Diubah',
                'type' => $foreignUnit->type->value,
                'status' => $foreignUnit->status->value,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('units', ['name' => 'Diubah']);
    }

    public function test_super_admin_can_delete_a_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->delete(route('admin.units.destroy', $unit))
            ->assertRedirect(route('admin.units.index'));

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
        $this->assertSame(
            1,
            ActivityLog::query()->where('event', ActivityEvent::Deleted->value)->count(),
        );
    }
}
