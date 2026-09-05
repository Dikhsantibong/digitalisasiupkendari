<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Machine;
use App\Models\User;

/**
 * Authorises machine actions: the user must hold the permission and the
 * machine's unit must fall inside their scope.
 */
class MachinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::MachineViewAny);
    }

    public function view(User $user, Machine $machine): bool
    {
        return $user->hasPermissionTo(PermissionName::MachineView)
            && $user->canAccessUnit($machine->unit_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::MachineCreate);
    }

    public function update(User $user, Machine $machine): bool
    {
        return $user->hasPermissionTo(PermissionName::MachineUpdate)
            && $user->canAccessUnit($machine->unit_id);
    }

    public function delete(User $user, Machine $machine): bool
    {
        return $user->hasPermissionTo(PermissionName::MachineDelete)
            && $user->canAccessUnit($machine->unit_id);
    }
}
