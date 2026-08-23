<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_create_a_user(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.users.store'), [
                'name' => 'Budi Santoso',
                'employee_id' => '99887766',
                'email' => 'budi@upkendari.test',
                'position' => 'Operator',
                'phone' => '081234567890',
                'is_active' => '1',
                'password' => 'rahasia-sekali',
                'password_confirmation' => 'rahasia-sekali',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'budi@upkendari.test',
            'employee_id' => '99887766',
            'is_active' => true,
        ]);
    }

    public function test_a_user_without_permission_cannot_create_a_user(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SiteLeader, $unit))
            ->post(route('admin.users.store'), [
                'name' => 'Tidak Sah',
                'email' => 'tidak-sah@upkendari.test',
                'is_active' => '1',
                'password' => 'rahasia-sekali',
                'password_confirmation' => 'rahasia-sekali',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'tidak-sah@upkendari.test']);
    }

    public function test_updating_a_user_without_a_password_keeps_the_existing_one(): void
    {
        $target = User::factory()->create();
        $originalPassword = $target->password;

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->put(route('admin.users.update', $target), [
                'name' => 'Nama Baru',
                'employee_id' => $target->employee_id,
                'email' => $target->email,
                'position' => 'Site Leader',
                'phone' => $target->phone,
                'is_active' => '1',
                'password' => '',
            ])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame('Nama Baru', $target->name);
        $this->assertSame($originalPassword, $target->password);
    }

    public function test_a_role_can_be_assigned_to_a_user_at_a_unit(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $target = User::factory()->create();
        $unit = Unit::factory()->create();
        $role = Role::query()->where('name', RoleName::Operator->value)->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('admin.users.assignments.store', $target), [
                'role_id' => $role->id,
                'unit_id' => $unit->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $target->id,
            'role_id' => $role->id,
            'unit_id' => $unit->id,
            'assigned_by' => $superAdmin->id,
        ]);

        $this->assertTrue($target->fresh()->canAccessUnit($unit));

        $this->assertDatabaseHas('activity_logs', [
            'event' => ActivityEvent::RoleAssigned->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_a_unit_scoped_role_cannot_be_assigned_without_a_unit(): void
    {
        $target = User::factory()->create();
        $role = Role::query()->where('name', RoleName::Operator->value)->firstOrFail();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.users.assignments.store', $target), [
                'role_id' => $role->id,
            ])
            ->assertSessionHasErrors('unit_id');

        $this->assertDatabaseCount('role_assignments', 1);
    }

    public function test_a_service_unit_role_cannot_be_assigned_without_a_service_unit(): void
    {
        $target = User::factory()->create();
        $role = Role::query()->where('name', RoleName::ManagerUl->value)->firstOrFail();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.users.assignments.store', $target), [
                'role_id' => $role->id,
                'unit_id' => Unit::factory()->create()->id,
            ])
            ->assertSessionHasErrors('service_unit_id');
    }

    public function test_an_assignment_can_be_revoked(): void
    {
        $unit = Unit::factory()->create();
        $target = User::factory()->create();
        $assignment = $target->assignRole(RoleName::Operator, $unit);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->delete(route('admin.users.assignments.destroy', [$target, $assignment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('role_assignments', ['id' => $assignment->id]);
        $this->assertFalse($target->fresh()->canAccessUnit($unit));
    }

    public function test_an_assignment_belonging_to_another_user_cannot_be_revoked(): void
    {
        $unit = Unit::factory()->create();
        $owner = User::factory()->create();
        $assignment = $owner->assignRole(RoleName::Operator, $unit);
        $other = User::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->delete(route('admin.users.assignments.destroy', [$other, $assignment]))
            ->assertNotFound();

        $this->assertDatabaseHas('role_assignments', ['id' => $assignment->id]);
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($superAdmin)
            ->delete(route('admin.users.destroy', $superAdmin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_a_manager_only_sees_users_assigned_within_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create();

        $insider = User::factory()->create();
        $insider->assignRole(RoleName::Operator, $unit);

        $outsider = User::factory()->create();
        $outsider->assignRole(RoleName::Operator, Unit::factory()->create());

        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $response = $this->actingAs($manager)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/users/index'));

        /** The manager sees the insider and their own record, but not the outsider. */
        $visibleIds = collect($response->viewData('page')['props']['users']['data'])
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$insider->id, $manager->id], $visibleIds);
        $this->assertNotContains($outsider->id, $visibleIds);
    }

    public function test_a_deactivated_user_is_signed_out_on_their_next_request(): void
    {
        $unit = Unit::factory()->create();
        $operator = $this->userWithRole(RoleName::Operator, $unit);

        $this->actingAs($operator)->get(route('dashboard'))->assertOk();

        $operator->forceFill(['is_active' => false])->save();

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
