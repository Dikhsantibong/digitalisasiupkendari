<?php

namespace App\Http\Controllers;

use App\Enums\CertificateStatus;
use App\Enums\PermissionName;
use App\Enums\UnitStatus;
use App\Models\ActivityLog;
use App\Models\DailyEngineReport;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
use App\Models\Inspection;
use App\Models\K3ActivityPlan;
use App\Models\OperatorLogsheet;
use App\Models\ReportPeriod;
use App\Models\SecurityPatrol;
use App\Models\ServiceRequest;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\K3\K3MonitoringService;
use App\Support\Indonesian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page after sign in. Aggregates cross-module data (operasi,
 * pemeliharaan, K3) into per-module chart sections — everything scoped to the
 * units the viewer can see, and each section gated by permission so every role
 * gets its own dashboard (Super Admin, holding all permissions, sees all).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly K3MonitoringService $monitoring) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        /** @var Collection<int, Unit> $units */
        $units = Unit::query()->visibleTo($user)->with('serviceUnit:id,name')->orderBy('name')->get();

        $unitIds = $units->pluck('id')->all();
        $now = Carbon::now();

        $has = fn (PermissionName $p): bool => $user->hasPermissionTo($p);
        $canOperasi = $has(PermissionName::OperasiInputView) || $has(PermissionName::OperatorLogsheetView);
        $canHar = $has(PermissionName::HarInputView) || $has(PermissionName::HarLaporanView);
        $canK3 = $has(PermissionName::K3InputView) || $has(PermissionName::K3LaporanView) || $has(PermissionName::K3MonitoringView);
        $canUnits = $user->can('viewAny', Unit::class);

        return Inertia::render('dashboard', [
            'scope' => ['operasi' => $canOperasi, 'har' => $canHar, 'k3' => $canK3, 'units' => $canUnits],
            'periodLabel' => Indonesian::monthName($now->month).' '.$now->year,
            'summary' => [
                'units' => $units->count(),
                'serviceUnits' => ServiceUnit::query()->visibleTo($user)->count(),
                'activeUnits' => $units->where('is_active', true)->count(),
                'installedCapacity' => round((float) $units->sum('installed_capacity_mw'), 2),
                'users' => $user->can('viewAny', User::class) ? $this->visibleUserCount($user) : null,
            ],
            'kpis' => $this->kpis($unitIds, $now, $canOperasi, $canHar, $canK3),
            'moduleActivity' => $this->moduleActivity($unitIds, $now, $canOperasi, $canHar, $canK3),
            'operasi' => $canOperasi ? [
                'daily_peak' => $this->dailyPeak($unitIds, $now),
                'logsheet_status' => $this->logsheetStatus($unitIds, $now),
            ] : null,
            'har' => $canHar ? $this->har($unitIds, $now) : null,
            'k3' => $canK3 ? [
                's_curve' => $this->sCurve($unitIds, $now),
                'cert_status' => $this->certStatus($unitIds),
                'apar_status' => $this->aparStatus($unitIds, $now),
                'patrol_top' => $this->patrolTop($unitIds, $now),
            ] : null,
            'statusBreakdown' => $canUnits
                ? collect(UnitStatus::cases())->map(fn (UnitStatus $s): array => [
                    'status' => $s->value, 'label' => $s->label(), 'tone' => $s->tone(), 'total' => $units->where('status', $s)->count(),
                ])->values()->all()
                : [],
            'units' => $canUnits ? $units->map(fn (Unit $unit): array => [
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

    /**
     * @param  list<int>  $unitIds
     * @return list<array{key: string, label: string, value: int, hint: string}>
     */
    private function kpis(array $unitIds, Carbon $now, bool $canOperasi, bool $canHar, bool $canK3): array
    {
        if ($unitIds === []) {
            return [];
        }

        $periodIds = $this->periodIds($unitIds, $now->year, $now->month);
        $bulan = Indonesian::monthName($now->month);
        $kpis = [];

        if ($canOperasi) {
            $kpis[] = ['key' => 'logsheet', 'label' => 'Logsheet Operator', 'value' => OperatorLogsheet::query()->whereIn('unit_id', $unitIds)->whereYear('log_date', $now->year)->whereMonth('log_date', $now->month)->count(), 'hint' => "Lembar {$bulan}"];
        }
        if ($canHar) {
            $kpis[] = ['key' => 'wo', 'label' => 'Work Order', 'value' => WorkOrder::query()->whereIn('unit_id', $unitIds)->whereIn('report_period_id', $periodIds)->count(), 'hint' => $bulan];
        }
        if ($canK3) {
            $kpis[] = ['key' => 'patrol', 'label' => 'Scan Patroli', 'value' => (int) SecurityPatrol::query()->whereIn('unit_id', $unitIds)->whereYear('patrol_date', $now->year)->whereMonth('patrol_date', $now->month)->sum('total_scan'), 'hint' => $bulan];
            $kpis[] = ['key' => 'cert_expired', 'label' => 'Sertifikat Expired', 'value' => collect($this->certStatus($unitIds))->firstWhere('type', CertificateStatus::Expired->label())['count'] ?? 0, 'hint' => 'Perlu uji ulang'];
        }

        return $kpis;
    }

    // ── Operasi ──────────────────────────────────────────────────────────────

    /**
     * @param  list<int>  $unitIds
     * @return array{month_label: string, points: list<array{label: string, value: float}>}
     */
    private function dailyPeak(array $unitIds, Carbon $now): array
    {
        $days = (int) $now->daysInMonth;
        $peak = array_fill(1, $days, 0.0);

        if ($unitIds !== []) {
            $reports = DailyEngineReport::query()
                ->whereIn('unit_id', $unitIds)->whereYear('report_date', $now->year)->whereMonth('report_date', $now->month)
                ->get(['report_date', 'beban_puncak_pagi_kw', 'beban_puncak_malam_kw']);

            foreach ($reports as $report) {
                $d = (int) $report->report_date->day;
                if (isset($peak[$d])) {
                    $peak[$d] = max($peak[$d], (float) $report->beban_puncak_pagi_kw, (float) $report->beban_puncak_malam_kw);
                }
            }
        }

        $points = [];
        for ($d = 1; $d <= $days; $d++) {
            $points[] = ['label' => (string) $d, 'value' => round($peak[$d], 1)];
        }

        return ['month_label' => Indonesian::monthName($now->month).' '.$now->year, 'points' => $points];
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<array{type: string, count: int}>
     */
    private function logsheetStatus(array $unitIds, Carbon $now): array
    {
        if ($unitIds === []) {
            return [];
        }

        $rows = OperatorLogsheet::query()
            ->whereIn('unit_id', $unitIds)->whereYear('log_date', $now->year)->whereMonth('log_date', $now->month)
            ->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');

        $labels = ['draft' => 'Draft', 'submitted' => 'Terkirim'];
        $out = [];
        foreach ($labels as $key => $label) {
            $count = (int) ($rows[$key] ?? 0);
            if ($count > 0) {
                $out[] = ['type' => $label, 'count' => $count];
            }
        }

        return $out;
    }

    // ── Pemeliharaan (HAR) ───────────────────────────────────────────────────

    /**
     * @param  list<int>  $unitIds
     * @return array{trend: list<array{label: string, wo: int, sr: int, cost: float}>, by_type: list<array{type: string, count: int}>, by_status: list<array{type: string, count: int}>}
     */
    private function har(array $unitIds, Carbon $now): array
    {
        return [
            'trend' => $this->trend($unitIds, $now),
            'by_type' => $this->woGrouped($unitIds, 'maintenance_type_id', 'maintenanceType'),
            'by_status' => $this->woGrouped($unitIds, 'wo_status_id', 'status'),
        ];
    }

    /**
     * WO vs SR volume and maintenance cost over the last six months.
     *
     * @param  list<int>  $unitIds
     * @return list<array{label: string, wo: int, sr: int, cost: float}>
     */
    private function trend(array $unitIds, Carbon $now): array
    {
        $months = [];
        $cursor = $now->copy()->startOfMonth()->subMonths(5);
        for ($i = 0; $i < 6; $i++) {
            $months[] = ['year' => $cursor->year, 'month' => $cursor->month, 'label' => Indonesian::monthName($cursor->month).' '.substr((string) $cursor->year, 2)];
            $cursor->addMonth();
        }

        if ($unitIds === []) {
            return array_map(fn (array $m): array => ['label' => $m['label'], 'wo' => 0, 'sr' => 0, 'cost' => 0.0], $months);
        }

        $periods = ReportPeriod::query()
            ->whereIn('unit_id', $unitIds)
            ->where(function ($query) use ($months): void {
                foreach ($months as $m) {
                    $query->orWhere(fn ($q) => $q->where('year', $m['year'])->where('month', $m['month']));
                }
            })
            ->get(['id', 'year', 'month']);

        $idsByKey = $periods->groupBy(fn (ReportPeriod $p): string => $p->year.'-'.$p->month)->map(fn ($g) => $g->pluck('id')->all());
        $allIds = $periods->pluck('id')->all();

        $wo = WorkOrder::query()->whereIn('report_period_id', $allIds)->select('report_period_id', DB::raw('count(*) as c'))->groupBy('report_period_id')->pluck('c', 'report_period_id');
        $sr = ServiceRequest::query()->whereIn('report_period_id', $allIds)->select('report_period_id', DB::raw('count(*) as c'))->groupBy('report_period_id')->pluck('c', 'report_period_id');
        $cost = WorkOrder::query()->whereIn('report_period_id', $allIds)
            ->select('report_period_id', DB::raw('COALESCE(SUM(service_cost),0) + COALESCE(SUM(material_cost),0) as c'))
            ->groupBy('report_period_id')->pluck('c', 'report_period_id');

        return array_map(function (array $m) use ($idsByKey, $wo, $sr, $cost): array {
            $ids = $idsByKey->get($m['year'].'-'.$m['month'], []);

            return [
                'label' => $m['label'],
                'wo' => (int) collect($ids)->sum(fn ($id): int => (int) ($wo[$id] ?? 0)),
                'sr' => (int) collect($ids)->sum(fn ($id): int => (int) ($sr[$id] ?? 0)),
                'cost' => (float) collect($ids)->sum(fn ($id): float => (float) ($cost[$id] ?? 0)),
            ];
        }, $months);
    }

    /**
     * Work Orders grouped by a foreign key, resolved to the related code.
     *
     * @param  list<int>  $unitIds
     * @return list<array{type: string, count: int}>
     */
    private function woGrouped(array $unitIds, string $column, string $relation): array
    {
        if ($unitIds === []) {
            return [];
        }

        return WorkOrder::query()
            ->whereIn('unit_id', $unitIds)->whereNotNull($column)
            ->with("{$relation}:id,code")
            ->select($column, DB::raw('count(*) as c'))
            ->groupBy($column)
            ->get()
            ->map(fn (WorkOrder $wo): array => ['type' => $wo->{$relation}?->code ?? 'Lainnya', 'count' => (int) $wo->c])
            ->sortByDesc('count')->values()->all();
    }

    // ── K3 & Keamanan ────────────────────────────────────────────────────────

    /**
     * @param  list<int>  $unitIds
     * @return array{days: int, month_label: string, plan_total: int, real_total: int, points: list<array{day: int, plan: int, real: int}>}
     */
    private function sCurve(array $unitIds, Carbon $now): array
    {
        $days = (int) $now->daysInMonth;
        $planPerDay = array_fill(1, $days, 0);
        $realPerDay = array_fill(1, $days, 0);

        if ($unitIds !== []) {
            $plans = K3ActivityPlan::query()
                ->whereIn('unit_id', $unitIds)->where('year', $now->year)->where('month', $now->month)
                ->get(['plan_days', 'real_days']);

            foreach ($plans as $plan) {
                foreach (array_keys($plan->plan_days ?? []) as $day) {
                    if (isset($planPerDay[(int) $day])) {
                        $planPerDay[(int) $day]++;
                    }
                }
                foreach (array_keys($plan->real_days ?? []) as $day) {
                    if (isset($realPerDay[(int) $day])) {
                        $realPerDay[(int) $day]++;
                    }
                }
            }
        }

        $points = [];
        $planCum = 0;
        $realCum = 0;
        for ($d = 1; $d <= $days; $d++) {
            $planCum += $planPerDay[$d];
            $realCum += $realPerDay[$d];
            $points[] = ['day' => $d, 'plan' => $planCum, 'real' => $realCum];
        }

        return ['days' => $days, 'month_label' => Indonesian::monthName($now->month).' '.$now->year, 'plan_total' => $planCum, 'real_total' => $realCum, 'points' => $points];
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<array{type: string, count: int}>
     */
    private function certStatus(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        $counts = [];
        foreach (EquipmentCertificate::query()->whereIn('unit_id', $unitIds)->get(['uji_ulang_tanggal']) as $cert) {
            $status = $this->monitoring->statusFor($cert->uji_ulang_tanggal)['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $this->statusDonut($counts);
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<array{type: string, count: int}>
     */
    private function aparStatus(array $unitIds, Carbon $now): array
    {
        if ($unitIds === []) {
            return [];
        }

        $latest = FireExtinguisherCheck::query()
            ->whereIn('unit_id', $unitIds)
            ->where(fn ($q) => $q->where('year', '<', $now->year)->orWhere(fn ($q2) => $q2->where('year', $now->year)->where('month', '<=', $now->month)))
            ->orderByDesc('year')->orderByDesc('month')->get()
            ->groupBy('fire_extinguisher_id')->map(fn ($g) => $g->first());

        $counts = [];
        foreach (FireExtinguisher::query()->whereIn('unit_id', $unitIds)->where('is_active', true)->get(['id']) as $ext) {
            $status = $this->monitoring->statusFor($latest->get($ext->id)?->exp_date)['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $this->statusDonut($counts);
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{type: string, count: int}>
     */
    private function statusDonut(array $counts): array
    {
        $out = [];
        foreach (CertificateStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;
            if ($count > 0) {
                $out[] = ['type' => $status->label(), 'count' => $count];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<array{label: string, value: int}>
     */
    private function patrolTop(array $unitIds, Carbon $now): array
    {
        if ($unitIds === []) {
            return [];
        }

        return SecurityPatrol::query()
            ->whereIn('unit_id', $unitIds)
            ->whereYear('patrol_date', $now->year)->whereMonth('patrol_date', $now->month)
            ->with('location:id,code,name')
            ->get()
            ->groupBy('patrol_location_id')
            ->map(fn ($g): array => [
                'label' => trim(($g->first()->location?->code ?? '').' '.($g->first()->location?->name ?? '')) ?: 'Lokasi',
                'value' => (int) $g->sum('total_scan'),
            ])
            ->sortByDesc('value')->take(6)->values()->all();
    }

    // ── Overview ─────────────────────────────────────────────────────────────

    /**
     * @param  list<int>  $unitIds
     * @return list<array{label: string, value: int}>
     */
    private function moduleActivity(array $unitIds, Carbon $now, bool $canOperasi, bool $canHar, bool $canK3): array
    {
        if ($unitIds === []) {
            return [];
        }

        $periodIds = $this->periodIds($unitIds, $now->year, $now->month);
        $items = [];

        if ($canOperasi) {
            $items[] = ['label' => 'Input Harian', 'value' => DailyEngineReport::query()->whereIn('unit_id', $unitIds)->whereYear('report_date', $now->year)->whereMonth('report_date', $now->month)->count()];
            $items[] = ['label' => 'Logsheet', 'value' => OperatorLogsheet::query()->whereIn('unit_id', $unitIds)->whereYear('log_date', $now->year)->whereMonth('log_date', $now->month)->count()];
        }
        if ($canHar) {
            $items[] = ['label' => 'Work Order', 'value' => WorkOrder::query()->whereIn('unit_id', $unitIds)->whereIn('report_period_id', $periodIds)->count()];
            $items[] = ['label' => 'Service Request', 'value' => ServiceRequest::query()->whereIn('unit_id', $unitIds)->whereIn('report_period_id', $periodIds)->count()];
        }
        if ($canK3) {
            $items[] = ['label' => 'Inspeksi K3', 'value' => Inspection::query()->whereIn('unit_id', $unitIds)->where('year', $now->year)->where('month', $now->month)->count()];
            $items[] = ['label' => 'Scan Patroli', 'value' => (int) SecurityPatrol::query()->whereIn('unit_id', $unitIds)->whereYear('patrol_date', $now->year)->whereMonth('patrol_date', $now->month)->sum('total_scan')];
        }

        return $items;
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<int>
     */
    private function periodIds(array $unitIds, int $year, int $month): array
    {
        return ReportPeriod::query()->whereIn('unit_id', $unitIds)->where('year', $year)->where('month', $month)->pluck('id')->all();
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
