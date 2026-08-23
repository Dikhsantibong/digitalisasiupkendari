<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Unit;
use App\Models\User;

/**
 * Authorises unit actions with both layers required by the concept: the user
 * must hold the permission, and the unit must fall inside their scope.
 */
class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::UnitViewAny);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo(PermissionName::UnitView)
            && $user->canAccessUnit($unit);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::UnitCreate);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo(PermissionName::UnitUpdate)
            && $user->canAccessUnit($unit);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo(PermissionName::UnitDelete)
            && $user->canAccessUnit($unit);
    }
}
