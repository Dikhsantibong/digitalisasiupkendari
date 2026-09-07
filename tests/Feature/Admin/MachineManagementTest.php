<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEvent;
use App\Enums\FuelType;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class MachineManagementTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_guests_are_redirected_away_from_the_machine_list(): void
    {
        $this->get(route('admin.machines.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_permission_cannot_open_the_machine_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.machines.index'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_every_machine(): void
    {
        Machine::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/machines/index')
                ->has('machines.data', 3),
            );
    }

    public function test_a_manager_only_sees_machines_within_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $ownUnit = Unit::factory()->forServiceUnit($serviceUnit)->create();
        Machine::factory()->count(2)->forUnit($ownUnit)->create();
        Machine::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->get(route('admin.machines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('machines.data', 2));
    }

    public function test_super_admin_can_create_a_machine_and_the_action_is_logged(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($superAdmin)
            ->post(route('admin.machines.store'), [
                'unit_id' => $unit->id,
                'name' => 'MAK #1',
                'type' => '8 M 453 AK',
                'serial_number' => '26881',
                'capacity_kw' => '1.7',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.machines.index'));

        $machine = Machine::query()->where('name', 'MAK #1')->firstOrFail();

        $this->assertSame($unit->id, $machine->unit_id);
        $this->assertSame('8 M 453 AK', $machine->type);
        $this->assertEquals(1.7, $machine->capacity_kw);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superAdmin->id,
            'event' => ActivityEvent::Created->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_a_machine_can_be_given_a_fuel_type_and_lubricant_types(): void
    {
        $unit = Unit::factory()->create();
        $lubricantA = LubricantType::factory()->forUnit($unit)->create();
        $lubricantB = LubricantType::factory()->forUnit($unit)->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.machines.store'), [
                'unit_id' => $unit->id,
                'name' => 'MIRRLEES #1',
                'fuel_type' => FuelType::HsdMfo->value,
                'is_active' => '1',
                'lubricant_type_ids' => [$lubricantA->id, $lubricantB->id],
            ])
            ->assertRedirect(route('admin.machines.index'));

        $machine = Machine::query()->where('name', 'MIRRLEES #1')->firstOrFail();

        $this->assertSame(FuelType::HsdMfo, $machine->fuel_type);
        $this->assertEqualsCanonicalizing(
            [$lubricantA->id, $lubricantB->id],
            $machine->lubricantTypes()->pluck('lubricant_types.id')->all(),
        );
    }

    public function test_a_machine_cannot_take_a_lubricant_type_from_another_unit(): void
    {
        $unit = Unit::factory()->create();
        $foreignLubricant = LubricantType::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.machines.store'), [
                'unit_id' => $unit->id,
                'name' => 'MESIN Y',
                'is_active' => '1',
                'lubricant_type_ids' => [$foreignLubricant->id],
            ])
            ->assertSessionHasErrors('lubricant_type_ids.0');

        $this->assertDatabaseMissing('machines', ['name' => 'MESIN Y']);
    }

    public function test_creating_a_machine_requires_a_unit(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.machines.store'), [
                'name' => 'Tanpa Unit',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('unit_id');
    }

    public function test_an_operator_cannot_create_a_machine(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('admin.machines.store'), [
                'unit_id' => $unit->id,
                'name' => 'MESIN X',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('machines', ['name' => 'MESIN X']);
    }

    public function test_a_manager_cannot_update_a_machine_outside_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $foreignMachine = Machine::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->put(route('admin.machines.update', $foreignMachine), [
                'unit_id' => $foreignMachine->unit_id,
                'name' => 'Diubah',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('machines', ['name' => 'Diubah']);
    }

    public function test_super_admin_can_delete_a_machine(): void
    {
        $machine = Machine::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->delete(route('admin.machines.destroy', $machine))
            ->assertRedirect(route('admin.machines.index'));

        $this->assertDatabaseMissing('machines', ['id' => $machine->id]);
        $this->assertSame(
            1,
            ActivityLog::query()->where('event', ActivityEvent::Deleted->value)->count(),
        );
    }
}
