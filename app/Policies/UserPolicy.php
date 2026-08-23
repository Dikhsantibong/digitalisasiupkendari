<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::UserViewAny);
    }

    /**
     * A user is visible when the viewer has global access, is looking at their
     * own record, or the target is assigned somewhere within the viewer's scope.
     */
    public function view(User $user, User $target): bool
    {
        if (! $user->hasPermissionTo(PermissionName::UserView)) {
            return false;
        }

        if ($user->hasGlobalAccess() || $user->is($target)) {
            return true;
        }

        return $target->roleAssignments()
            ->where(function ($query) use ($user): void {
                $query->whereIn('unit_id', $user->accessibleUnitIds())
                    ->orWhereIn('service_unit_id', $user->accessibleServiceUnitIds());
            })
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::UserCreate);
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermissionTo(PermissionName::UserUpdate);
    }

    /**
     * Deleting your own account through access management would lock the
     * organisation out of its own administration, so it is refused here.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->hasPermissionTo(PermissionName::UserDelete)
            && ! $user->is($target);
    }
}
