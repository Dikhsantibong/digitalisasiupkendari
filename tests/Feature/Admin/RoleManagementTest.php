<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\RoleScope;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_review_every_role(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/roles/index')
                ->has('roles', count(RoleName::cases()))
                ->where('permissionTotal', count(PermissionName::cases())),
            );
    }

    public function test_a_manager_cannot_reach_role_management(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_change_the_permissions_of_a_role(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $role = Role::query()->where('name', RoleName::Operator->value)->firstOrFail();

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $role), [
                'name' => $role->name,
                'display_name' => 'Operator Unit',
                'scope' => $role->scope->value,
                'description' => 'Deskripsi baru',
                'permissions' => [
                    PermissionName::UnitView->value,
                    PermissionName::ReportUnitCreate->value,
                ],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role->refresh();

        $this->assertSame('Operator Unit', $role->display_name);
        $this->assertEqualsCanonicalizing(
            [PermissionName::UnitView->value, PermissionName::ReportUnitCreate->value],
            $role->permissions()->pluck('name')->all(),
        );

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superAdmin->id,
            'event' => ActivityEvent::PermissionsUpdated->value,
        ]);
    }

    public function test_changing_a_role_immediately_changes_what_its_holders_may_do(): void
    {
        $unit = Unit::factory()->create();
        $operator = $this->userWithRole(RoleName::Operator, $unit);

        $this->assertTrue($operator->hasPermissionTo(PermissionName::ReportUnitCreate));

        $role = Role::query()->where('name', RoleName::Operator->value)->firstOrFail();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->put(route('admin.roles.update', $role), [
                'name' => $role->name,
                'display_name' => $role->display_name,
                'scope' => $role->scope->value,
                'permissions' => [PermissionName::UnitView->value],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $operator->forgetAccessCache();

        $this->assertFalse($operator->hasPermissionTo(PermissionName::ReportUnitCreate));
    }

    public function test_the_identity_of_a_system_role_cannot_be_rewritten(): void
    {
        $role = Role::query()->where('name', RoleName::SiteLeader->value)->firstOrFail();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->put(route('admin.roles.update', $role), [
                'name' => 'diretas',
                'display_name' => 'Site Leader',
                'scope' => RoleScope::Global->value,
                'permissions' => [],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role->refresh();

        $this->assertSame(RoleName::SiteLeader->value, $role->name);
        $this->assertSame(RoleScope::Unit, $role->scope);
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $role = Role::query()->where('name', RoleName::Operator->value)->firstOrFail();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->delete(route('admin.roles.destroy', $role))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_super_admin_can_create_and_delete_a_custom_role(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($superAdmin)
            ->post(route('admin.roles.store'), [
                'name' => 'Pengawas Mutu',
                'display_name' => 'Pengawas Mutu',
                'scope' => RoleScope::Unit->value,
                'permissions' => [PermissionName::UnitView->value],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'pengawas_mutu')->firstOrFail();

        $this->assertFalse($role->is_system);
        $this->assertSame(1, $role->permissions()->count());

        $this->actingAs($superAdmin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_a_role_cannot_be_given_an_unknown_permission(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.roles.store'), [
                'name' => 'peran_palsu',
                'display_name' => 'Peran Palsu',
                'scope' => RoleScope::Unit->value,
                'permissions' => ['unit.hapus_semua'],
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'peran_palsu']);
    }
}
