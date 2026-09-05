<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\User;

/**
 * Authorises employee actions: the user must hold the permission and the
 * employee's unit must fall inside their scope.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::EmployeeViewAny);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo(PermissionName::EmployeeView)
            && $user->canAccessUnit($employee->unit_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::EmployeeCreate);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo(PermissionName::EmployeeUpdate)
            && $user->canAccessUnit($employee->unit_id);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo(PermissionName::EmployeeDelete)
            && $user->canAccessUnit($employee->unit_id);
    }
}
