<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_guests_are_redirected_away_from_the_employee_list(): void
    {
        $this->get(route('admin.employees.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_permission_cannot_open_the_employee_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.employees.index'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_every_employee(): void
    {
        Employee::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/employees/index')
                ->has('employees.data', 3),
            );
    }

    public function test_a_manager_only_sees_employees_within_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $ownUnit = Unit::factory()->forServiceUnit($serviceUnit)->create();
        Employee::factory()->count(2)->forUnit($ownUnit)->create();
        Employee::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('employees.data', 2));
    }

    public function test_super_admin_can_create_an_employee_and_the_action_is_logged(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($superAdmin)
            ->post(route('admin.employees.store'), [
                'unit_id' => $unit->id,
                'name' => 'Budi Santoso',
                'nip' => '1990123456',
                'position' => 'Operator',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('nip', '1990123456')->firstOrFail();

        $this->assertSame($unit->id, $employee->unit_id);
        $this->assertSame('Budi Santoso', $employee->name);
        $this->assertSame('Operator', $employee->position);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superAdmin->id,
            'event' => ActivityEvent::Created->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_creating_an_employee_requires_a_name(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), [
                'nip' => '123',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_creating_an_employee_requires_a_unique_nip(): void
    {
        $existing = Employee::factory()->create(['nip' => '555000']);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), [
                'name' => 'Duplikat NIP',
                'nip' => $existing->nip,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('nip');
    }

    public function test_an_operator_cannot_create_an_employee(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('admin.employees.store'), [
                'unit_id' => $unit->id,
                'name' => 'Pegawai X',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('employees', ['name' => 'Pegawai X']);
    }

    public function test_super_admin_can_delete_an_employee(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertSame(
            1,
            ActivityLog::query()->where('event', ActivityEvent::Deleted->value)->count(),
        );
    }
}
