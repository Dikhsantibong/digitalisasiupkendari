<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\UnitStatus;
use App\Models\ActivityLog;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\DashboardDummyData;
use App\Support\Indonesian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page after sign in. Renders one chart section per module
 * (operasi, pemeliharaan, K3, logistik, PdM), each gated by permission so every
 * role gets its own dashboard (Super Admin, holding all permissions, sees all).
 *
 * Module figures currently come from {@see DashboardDummyData} (flagged to the
 * page via `isDummy`); unit, user, and activity figures are real and scoped to
 * the units the viewer can see.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        /** @var Collection<int, Unit> $units */
        $units = Unit::query()->visibleTo($user)->with('serviceUnit:id,name')->orderBy('name')->get();

        $now = Carbon::now();

        $hasAny = fn (PermissionName ...$permissions): bool => collect($permissions)
            ->contains(fn (PermissionName $permission): bool => $user->hasPermissionTo($permission));

        $scope = [
            'operasi' => $hasAny(PermissionName::OperasiInputView, PermissionName::OperasiLaporanView, PermissionName::OperatorLogsheetView),
            'har' => $hasAny(PermissionName::HarInputView, PermissionName::HarLaporanView, PermissionName::HarExecutiveView),
            'k3' => $hasAny(PermissionName::K3InputView, PermissionName::K3LaporanView, PermissionName::K3MonitoringView),
            'logistik' => $hasAny(PermissionName::LogistikInputView, PermissionName::LogistikLaporanView),
            'pdm' => $hasAny(PermissionName::PdmInputView, PermissionName::PdmLaporanView),
            'units' => $user->can('viewAny', Unit::class),
        ];

        $dummy = new DashboardDummyData($now, $units->pluck('name')->all());

        return Inertia::render('dashboard', [
            'scope' => $scope,
            'isDummy' => true,
            'periodLabel' => Indonesian::monthName($now->month).' '.$now->year,
            'summary' => [
                'units' => $units->count(),
                'serviceUnits' => ServiceUnit::query()->visibleTo($user)->count(),
                'activeUnits' => $units->where('is_active', true)->count(),
                'installedCapacity' => round((float) $units->sum('installed_capacity_mw'), 2),
                'users' => $user->can('viewAny', User::class) ? $this->visibleUserCount($user) : null,
            ],
            'moduleHealth' => $dummy->moduleHealth($scope),
            'moduleActivity' => $dummy->moduleActivity($scope),
            'operasi' => $scope['operasi'] ? $dummy->operasi() : null,
            'har' => $scope['har'] ? $dummy->har() : null,
            'k3' => $scope['k3'] ? $dummy->k3() : null,
            'logistik' => $scope['logistik'] ? $dummy->logistik() : null,
            'pdm' => $scope['pdm'] ? $dummy->pdm() : null,
            'statusBreakdown' => $scope['units']
                ? collect(UnitStatus::cases())->map(fn (UnitStatus $s): array => [
                    'status' => $s->value, 'label' => $s->label(), 'tone' => $s->tone(), 'total' => $units->where('status', $s)->count(),
                ])->values()->all()
                : [],
            'units' => $scope['units'] ? $units->map(fn (Unit $unit): array => [
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
            ])->all() : [],
            'recentActivity' => $user->can('viewAny', ActivityLog::class) ? $this->recentActivity($user) : [],
        ]);
    }

    private function visibleUserCount(User $user): int
    {
        if ($user->hasGlobalAccess()) {
            return User::query()->count();
        }

        return User::query()
            ->whereHas('roleAssignments', function ($query) use ($user): void {
                $query->whereIn('unit_id', $user->accessibleUnitIds())->orWhereIn('service_unit_id', $user->accessibleServiceUnitIds());
            })
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(User $user): array
    {
        return ActivityLog::query()
            ->visibleTo($user)->with('user:id,name')->latest('created_at')->limit(8)->get()
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
