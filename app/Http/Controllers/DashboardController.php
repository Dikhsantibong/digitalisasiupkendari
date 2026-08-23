<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Models\ActivityLog;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page after sign in, showing only what the viewer's scope allows.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $units = Unit::query()
            ->visibleTo($user)
            ->with('serviceUnit:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('dashboard', [
            'summary' => [
                'units' => $units->count(),
                'serviceUnits' => ServiceUnit::query()->visibleTo($user)->count(),
                'activeUnits' => $units->where('is_active', true)->count(),
                'installedCapacity' => round((float) $units->sum('installed_capacity_mw'), 2),
                'users' => $user->can('viewAny', User::class)
                    ? $this->visibleUserCount($user)
                    : null,
            ],
            'statusBreakdown' => collect(UnitStatus::cases())
                ->map(fn (UnitStatus $status): array => [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'tone' => $status->tone(),
                    'total' => $units->where('status', $status)->count(),
                ])
                ->values()
                ->all(),
            'units' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'type' => $unit->type->label(),
                'status' => $unit->status->value,
                'status_label' => $unit->status->label(),
                'status_tone' => $unit->status->tone(),
                'service_unit' => $unit->serviceUnit?->name,
                'installed_capacity_mw' => $unit->installed_capacity_mw,
                'is_active' => $unit->is_active,
            ])->all(),
            'recentActivity' => $user->can('viewAny', ActivityLog::class)
                ? $this->recentActivity($user)
                : [],
        ]);
    }

    private function visibleUserCount(User $user): int
    {
        if ($user->hasGlobalAccess()) {
            return User::query()->count();
        }

        return User::query()
            ->whereHas('roleAssignments', function ($query) use ($user): void {
                $query->whereIn('unit_id', $user->accessibleUnitIds())
                    ->orWhereIn('service_unit_id', $user->accessibleServiceUnitIds());
            })
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(User $user): array
    {
        return ActivityLog::query()
            ->visibleTo($user)
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event->value,
                'event_label' => $log->event->label(),
                'tone' => $log->event->tone(),
                'description' => $log->description,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
