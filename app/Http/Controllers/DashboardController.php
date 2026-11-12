<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\UnitStatus;
use App\Models\ActivityLog;
use App\Models\LubricantType;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\HarDashboard;
use App\Services\Dashboard\K3Dashboard;
use App\Services\Dashboard\LogistikDashboard;
use App\Services\Dashboard\OperasiDashboard;
use App\Services\Dashboard\PdmDashboard;
use App\Services\Monitoring\InputCatalog;
use App\Services\Monitoring\InputCompleteness;
use App\Services\Operasi\UnitFuelTypes;
use App\Services\Reports\ApprovalInbox;
use App\Support\Indonesian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page after sign in. Renders one chart section per module
 * (operasi, pemeliharaan, K3, logistik, PdM), each gated by permission so every
 * role gets its own dashboard (Super Admin, holding all permissions, sees all).
 *
 * Every figure is real and scoped to the units the viewer can see: the module
 * sections come from {@see OperasiDashboard}, {@see HarDashboard},
 * {@see K3Dashboard}, {@see LogistikDashboard} and {@see PdmDashboard} (read
 * from the inputs of each module, PRD.md §8.10), the rings from the input
 * completeness ({@see InputCompleteness}).
 */
class DashboardController extends Controller
{
    /** @var array<string, list<string>> module => its InputCatalog groups */
    private const MODULE_GROUPS = [
        'operasi' => ['operasi', 'operasi_pengusahaan', 'operator'],
        'har' => ['har', 'har_pengusahaan'],
        'k3' => ['k3', 'k3_pengusahaan'],
        'logistik' => ['logistik'],
        'pdm' => ['pdm'],
    ];

    public function __construct(
        private readonly InputCompleteness $completeness,
        private readonly UnitFuelTypes $fuelTypes,
        private readonly OperasiDashboard $operasiDashboard,
        private readonly HarDashboard $harDashboard,
        private readonly K3Dashboard $k3Dashboard,
        private readonly LogistikDashboard $logistikDashboard,
        private readonly PdmDashboard $pdmDashboard,
        private readonly ApprovalInbox $inbox,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Kantor induk UP Kendari & Manager UL land on the Portal Pemantauan.
        if ($user->usesPortal()) {
            return redirect()->route('portal.index');
        }

        /** @var Collection<int, Unit> $units */
        $units = Unit::query()->visibleTo($user)->with('serviceUnit:id,name')->orderBy('name')->get();

        $now = Carbon::now();

        $hasAny = fn (PermissionName ...$permissions): bool => collect($permissions)
            ->contains(fn (PermissionName $permission): bool => $user->hasPermissionTo($permission));

        $scope = [
            // TL & Staf Operasi hold only the Pengusahaan permissions (Akses 2).
            'operasi' => $hasAny(PermissionName::OperasiInputView, PermissionName::OperasiLaporanView, PermissionName::OperatorLogsheetView, PermissionName::OperasiPengusahaanView),
            'har' => $hasAny(PermissionName::HarInputView, PermissionName::HarLaporanView, PermissionName::HarExecutiveView),
            'k3' => $hasAny(PermissionName::K3InputView, PermissionName::K3LaporanView, PermissionName::K3MonitoringView),
            'logistik' => $hasAny(PermissionName::LogistikInputView, PermissionName::LogistikLaporanView),
            'pdm' => $hasAny(PermissionName::PdmInputView, PermissionName::PdmLaporanView),
            'units' => $user->can('viewAny', Unit::class),
        ];

        $modules = array_keys(array_filter(array_intersect_key($scope, self::MODULE_GROUPS)));
        // Real: how complete the inputs are this month (Monitoring → Kelengkapan Input).
        $completeness = $modules === [] ? null : $this->completeness->build(
            $units,
            $now->month,
            $now->year,
            groups: array_merge(...array_map(fn (string $module): array => self::MODULE_GROUPS[$module], $modules)),
        );

        return Inertia::render('dashboard', [
            'scope' => $scope,
            'periodLabel' => Indonesian::monthName($now->month).' '.$now->year,
            'summary' => [
                'units' => $units->count(),
                'serviceUnits' => ServiceUnit::query()->visibleTo($user)->count(),
                'activeUnits' => $units->where('is_active', true)->count(),
                'installedCapacity' => round((float) $units->sum('installed_capacity_mw'), 2),
                'users' => $user->can('viewAny', User::class) ? $this->visibleUserCount($user) : null,
            ],
            'moduleHealth' => $this->moduleHealth($modules, $completeness),
            'moduleActivity' => $this->groupCompleteness($completeness),
            // Each module reads many sheets of every visible unit: loaded right after the page, one request per module.
            'operasi' => $scope['operasi'] ? Inertia::defer(fn (): array => [
                ...$this->operasiDashboard->build($units, $now, $this->unitFuels($units), $this->unitLubricants($units)),
                'completeness' => $this->menuCompleteness($completeness, 'operasi_pengusahaan', $now),
            ], 'operasi') : null,
            'har' => $scope['har'] ? Inertia::defer(fn (): array => $this->harDashboard->build($units, $now), 'har') : null,
            'k3' => $scope['k3'] ? Inertia::defer(fn (): array => $this->k3Dashboard->build($units, $now), 'k3') : null,
            'logistik' => $scope['logistik'] ? Inertia::defer(fn (): array => $this->logistikDashboard->build($units, $now), 'logistik') : null,
            'pdm' => $scope['pdm'] ? Inertia::defer(fn (): array => $this->pdmDashboard->build($units, $now), 'pdm') : null,
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
            // Team Leader / Koordinator / Project Leader approve and input: their inbox and the inputs left to fill.
            'approvals' => ApprovalInbox::applies($user) ? [
                'queue' => $this->inbox->queue($user, $units),
                'requests' => $this->inbox->requests($user),
                'acknowledgements' => array_slice($this->inbox->acknowledgements($user), 0, 5),
            ] : null,
            'inputTodo' => $this->inputTodo($completeness, $now),
        ]);
    }

    /**
     * Executive rings: per module the average input completeness of its groups.
     *
     * @param  list<string>  $modules
     * @param  array<string, mixed>|null  $completeness
     * @return list<array{key: string, label: string, value: float, caption: string}>
     */
    private function moduleHealth(array $modules, ?array $completeness): array
    {
        $labels = ['operasi' => 'Operasi', 'har' => 'Pemeliharaan', 'k3' => 'K3 & Keamanan', 'logistik' => 'Logistik', 'pdm' => 'PdM'];

        return array_map(function (string $module) use ($completeness, $labels): array {
            $values = array_filter(
                array_map(fn (string $group): ?int => $completeness['groups'][$group] ?? null, self::MODULE_GROUPS[$module]),
                fn (?int $value): bool => $value !== null,
            );

            return [
                'key' => $module,
                'label' => $labels[$module],
                'value' => $values === [] ? 0.0 : round(array_sum($values) / count($values), 1),
                'caption' => 'Kelengkapan input',
            ];
        }, $modules);
    }

    /**
     * Input completeness per group (percent), for the executive bar list.
     *
     * @param  array<string, mixed>|null  $completeness
     * @return list<array{label: string, value: int}>
     */
    private function groupCompleteness(?array $completeness): array
    {
        $rows = [];
        foreach ($completeness['groups'] ?? [] as $group => $percent) {
            if ($percent !== null) {
                $rows[] = ['label' => InputCatalog::GROUPS[$group] ?? $group, 'value' => $percent];
            }
        }

        return $rows;
    }

    /**
     * Completeness of each menu of one group this month (average over the
     * units), with a link to fill it.
     *
     * @param  array<string, mixed>|null  $completeness
     * @return list<array{label: string, percent: int|null, url: string}>
     */
    /**
     * The input menus of the viewer's modules still incomplete this month,
     * least complete first, with a link to fill them (average over the units).
     *
     * @param  array<string, mixed>|null  $completeness
     * @return list<array{label: string, group: string, percent: int, url: string}>
     */
    private function inputTodo(?array $completeness, Carbon $now): array
    {
        if ($completeness === null || $completeness['units'] === []) {
            return [];
        }

        $rows = [];
        foreach ($completeness['entries'] as $entry) {
            $percents = array_filter(
                array_map(fn (array $unit): ?int => $unit['cells'][$entry['key']]['percent'] ?? null, $completeness['units']),
                fn (?int $value): bool => $value !== null,
            );
            if ($percents === []) {
                continue;
            }
            $percent = (int) round(array_sum($percents) / count($percents));
            if ($percent < 100) {
                $rows[] = [
                    'label' => $entry['label'],
                    'group' => InputCatalog::GROUPS[$entry['group']] ?? $entry['group'],
                    'percent' => $percent,
                    'url' => route($entry['route'], [...$entry['params'], 'month' => $now->month, 'year' => $now->year]),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

        return array_slice($rows, 0, 8);
    }

    private function menuCompleteness(?array $completeness, string $group, Carbon $now): array
    {
        if ($completeness === null) {
            return [];
        }

        $rows = [];
        foreach ($completeness['entries'] as $entry) {
            if ($entry['group'] !== $group) {
                continue;
            }

            $percents = array_filter(
                array_map(fn (array $unit): ?int => $unit['cells'][$entry['key']]['percent'] ?? null, $completeness['units']),
                fn (?int $value): bool => $value !== null,
            );
            $rows[] = [
                'label' => $entry['label'],
                'percent' => $percents === [] ? null : (int) round(array_sum($percents) / count($percents)),
                'url' => route($entry['route'], [...$entry['params'], 'month' => $now->month, 'year' => $now->year]),
            ];
        }

        return $rows;
    }

    /**
     * The jenis BBM of the visible units, from the master (each code once).
     *
     * @param  Collection<int, Unit>  $units
     * @return list<array{code: string, name: string}>
     */
    private function unitFuels(Collection $units): array
    {
        return collect($units)
            ->flatMap(fn (Unit $unit): array => $this->fuelTypes->forUnit($unit))
            ->unique('code')
            ->map(fn (array $fuel): array => ['code' => $fuel['code'], 'name' => $fuel['name']])
            ->values()
            ->all();
    }

    /**
     * The jenis pelumas of the visible units, from the master (each name once).
     *
     * @param  Collection<int, Unit>  $units
     * @return list<string>
     */
    private function unitLubricants(Collection $units): array
    {
        return LubricantType::query()
            ->whereIn('unit_id', $units->pluck('id'))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
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
