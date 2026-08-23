<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_catalogue_seeds_every_permission_and_system_role(): void
    {
        $this->assertSame(count(PermissionName::cases()), Permission::query()->count());
        $this->assertSame(count(RoleName::cases()), Role::query()->count());

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->where('name', $roleName->value)->firstOrFail();

            $this->assertTrue($role->is_system);
            $this->assertSame($roleName->scope(), $role->scope);
            $this->assertSame(
                count($roleName->defaultPermissions()),
                $role->permissions()->count(),
            );
        }
    }

    public function test_super_admin_holds_every_permission_and_sees_every_unit(): void
    {
        Unit::factory()->count(3)->create();

        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->hasGlobalAccess());
        $this->assertCount(3, $superAdmin->accessibleUnitIds());

        foreach (PermissionName::cases() as $permission) {
            $this->assertTrue($superAdmin->can($permission->value));
        }
    }

    public function test_a_manager_sees_every_unit_beneath_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $ownUnits = Unit::factory()->count(2)->forServiceUnit($serviceUnit)->create();
        $otherUnit = Unit::factory()->create();

        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->assertFalse($manager->hasGlobalAccess());
        $this->assertEqualsCanonicalizing(
            $ownUnits->pluck('id')->all(),
            $manager->accessibleUnitIds(),
        );
        $this->assertTrue($manager->canAccessUnit($ownUnits->first()));
        $this->assertFalse($manager->canAccessUnit($otherUnit));
    }

    public function test_a_manager_reaches_units_added_to_their_service_unit_later(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->assertCount(0, $manager->accessibleUnitIds());

        $newUnit = Unit::factory()->forServiceUnit($serviceUnit)->create();
        $manager->forgetAccessCache();

        $this->assertTrue($manager->canAccessUnit($newUnit));
    }

    public function test_a_unit_scoped_role_reaches_only_its_own_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create();
        $sibling = Unit::factory()->forServiceUnit($serviceUnit)->create();

        $operator = $this->userWithRole(RoleName::Operator, $unit);

        $this->assertSame([$unit->id], $operator->accessibleUnitIds());
        $this->assertFalse($operator->canAccessUnit($sibling));
        $this->assertTrue($operator->hasPermissionTo(PermissionName::ReportUnitCreate));
        $this->assertFalse($operator->hasPermissionTo(PermissionName::ReportUnitApprove));
    }

    public function test_a_user_may_hold_several_assignments(): void
    {
        $first = Unit::factory()->create();
        $second = Unit::factory()->create();

        $user = User::factory()->create();
        $user->assignRole(RoleName::SiteLeader, $first);
        $user->assignRole(RoleName::SiteLeader, $second);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $user->accessibleUnitIds(),
        );
    }

    public function test_assigning_the_same_role_and_scope_twice_creates_one_record(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create();

        $user->assignRole(RoleName::Operator, $unit);
        $user->assignRole(RoleName::Operator, $unit);

        $this->assertSame(1, $user->roleAssignments()->count());
    }

    public function test_a_unit_scoped_role_cannot_be_assigned_to_a_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $user->assignRole(RoleName::Operator, $serviceUnit);
    }

    public function test_a_service_unit_role_cannot_be_assigned_to_a_unit(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $user->assignRole(RoleName::ManagerUl, $unit);
    }

    public function test_revoking_a_role_removes_the_access_it_granted(): void
    {
        $unit = Unit::factory()->create();
        $operator = $this->userWithRole(RoleName::Operator, $unit);

        $this->assertTrue($operator->canAccessUnit($unit));

        $operator->revokeRole(RoleName::Operator, $unit);

        $this->assertFalse($operator->canAccessUnit($unit));
        $this->assertFalse($operator->hasPermissionTo(PermissionName::ReportUnitCreate));
    }

    public function test_a_user_without_assignments_has_no_access(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($user->hasGlobalAccess());
        $this->assertSame([], $user->accessibleUnitIds());
        $this->assertFalse($user->canAccessUnit($unit));
        $this->assertFalse($user->can(PermissionName::UnitViewAny->value));
    }

    public function test_a_permission_added_later_reaches_super_admin_without_reseeding(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->assertTrue($superAdmin->can('report_fuel.approve'));
    }
}
