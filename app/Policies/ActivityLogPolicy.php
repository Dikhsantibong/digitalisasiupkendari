<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::ActivityLogViewAny);
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        if (! $user->hasPermissionTo(PermissionName::ActivityLogViewAny)) {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        return $user->canAccessUnit($activityLog->unit_id)
            || $user->canAccessServiceUnit($activityLog->service_unit_id);
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::ActivityLogExport);
    }
}
