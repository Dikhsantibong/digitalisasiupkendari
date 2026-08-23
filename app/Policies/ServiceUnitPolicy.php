<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ServiceUnit;
use App\Models\User;

class ServiceUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::ServiceUnitViewAny);
    }

    public function view(User $user, ServiceUnit $serviceUnit): bool
    {
        return $user->hasPermissionTo(PermissionName::ServiceUnitView)
            && $user->canAccessServiceUnit($serviceUnit);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::ServiceUnitCreate);
    }

    public function update(User $user, ServiceUnit $serviceUnit): bool
    {
        return $user->hasPermissionTo(PermissionName::ServiceUnitUpdate)
            && $user->canAccessServiceUnit($serviceUnit);
    }

    public function delete(User $user, ServiceUnit $serviceUnit): bool
    {
        return $user->hasPermissionTo(PermissionName::ServiceUnitDelete)
            && $user->canAccessServiceUnit($serviceUnit);
    }
}
