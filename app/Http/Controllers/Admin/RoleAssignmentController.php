<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleAssignmentRequest;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Grants and revokes a user's roles. Kept apart from UserController so that the
 * account record and the access record stay separately auditable.
 */
class RoleAssignmentController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function store(RoleAssignmentRequest $request, User $user): RedirectResponse
    {
        $this->authorize('assign', Role::class);

        $role = Role::query()->findOrFail($request->integer('role_id'));

        $scope = match (true) {
            $role->scope->requiresUnit() => Unit::query()->findOrFail($request->integer('unit_id')),
            $role->scope->requiresServiceUnit() => ServiceUnit::query()->findOrFail($request->integer('service_unit_id')),
            default => null,
        };

        $assignment = $user->assignRole($role, $scope, $request->user());

        $this->activityLogger->log(
            ActivityEvent::RoleAssigned,
            "Menugaskan role {$role->display_name} kepada {$user->name} pada {$assignment->locationLabel()}",
            $user,
            properties: ['role' => $role->name, 'scope' => $assignment->locationLabel()],
            unit: $assignment->unit_id,
            serviceUnit: $assignment->service_unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role berhasil ditugaskan.']);

        return back();
    }

    public function destroy(Request $request, User $user, RoleAssignment $assignment): RedirectResponse
    {
        $this->authorize('assign', Role::class);

        abort_unless($assignment->user_id === $user->getKey(), 404);

        $assignment->loadMissing(['role:id,name,display_name', 'unit:id,name', 'serviceUnit:id,name']);

        $description = "Mencabut role {$assignment->role->display_name} dari {$user->name} pada {$assignment->locationLabel()}";
        $unitId = $assignment->unit_id;
        $serviceUnitId = $assignment->service_unit_id;

        $assignment->delete();
        $user->forgetAccessCache();

        $this->activityLogger->log(
            ActivityEvent::RoleRevoked,
            $description,
            $user,
            unit: $unitId,
            serviceUnit: $serviceUnitId,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penugasan role berhasil dicabut.']);

        return back();
    }
}
