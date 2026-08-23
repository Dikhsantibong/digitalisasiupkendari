<?php

namespace Tests\Concerns;

use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Shared setup for tests that need the role and permission catalogue in place.
 */
trait InteractsWithAccessControl
{
    protected function seedAccessControl(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Create a user holding the given system role at the given place.
     */
    protected function userWithRole(
        RoleName $role,
        ServiceUnit|Unit|null $scope = null,
    ): User {
        $user = User::factory()->create();
        $user->assignRole($role, $scope);

        return $user->fresh();
    }
}
