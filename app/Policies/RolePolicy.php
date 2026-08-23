<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::RoleViewAny);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RoleView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::RoleCreate);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RoleUpdate);
    }

    /**
     * System roles are part of the organisation's structure and may not be
     * removed, only re-permissioned.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissionName::RoleDelete)
            && ! $role->is_system;
    }

    public function assign(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::RoleAssign);
    }
}
