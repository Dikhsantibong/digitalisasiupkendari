<?php

namespace App\Services\Dashboard;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The Pemeliharaan section of the dashboard, read from the Work Order and
 * Service Request of the visible units. A WO / SR belongs to the month of its
 * report period; biaya = jasa (service_cost) + material (material_cost) of the
 * WO. Backlog = every WO whose status is not closed, oldest first.
 */
class HarDashboard
{
    /**
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    public function build(Collection $units, Carbon $now): array
    {
        $unitIds = $units->pluck('id')->all();
        $months = DashboardSupport::months($now);
        $first = $months[0];

        $workOrders = $this->inWindow(WorkOrder::query(), $unitIds, $first['month'], $first['year'])
            ->with(['status:id,name,is_closed', 'maintenanceType:id,code,name'])
            ->get(['work_orders.*', 'report_periods.month as period_month', 'report_periods.year as period_year']);
        $requests = $this->inWindow(ServiceRequest::query(), $unitIds, $first['month'], $first['year'])
            ->get(['service_requests.status', 'report_periods.month as period_month', 'report_periods.year as period_year']);

        $woOf = fn (array $m) => $workOrders->filter(fn (WorkOrder $wo): bool => (int) $wo->period_month === $m['month'] && (int) $wo->period_year === $m['year']);
        $srOf = fn (array $m) => $requests->filter(fn (ServiceRequest $sr): bool => (int) $sr->period_month === $m['month'] && (int) $sr->period_year === $m['year']);

        $woCount = array_map(fn (array $m): int => $woOf($m)->count(), $months);
        $srCount = array_map(fn (array $m): int => $srOf($m)->count(), $months);
        $srOpen = array_map(fn (array $m): int => $srOf($m)->where('status', ServiceRequestStatus::Open)->count(), $months);
        $closedPercent = array_map(function (array $m) use ($woOf): float {
            $all = $woOf($m);

            return $all->isEmpty() ? 0.0 : round($all->filter(fn (WorkOrder $wo): bool => (bool) $wo->status?->is_closed)->count() / $all->count() * 100, 1);
        }, $months);
        $jasa = array_map(fn (array $m): float => round((float) $woOf($m)->sum('service_cost'), 2), $months);
        $material = array_map(fn (array $m): float => round((float) $woOf($m)->sum('material_cost'), 2), $months);
        $cost = array_map(fn (float $a, float $b): float => $a + $b, $jasa, $material);

        $thisMonth = $woOf(end($months));
        $backlog = WorkOrder::query()
            ->whereIn('unit_id', $unitIds)
            ->where(fn (Builder $query) => $query->whereNull('wo_status_id')->orWhereHas('status', fn (Builder $q) => $q->where('is_closed', false)))
            ->with(['status:id,name', 'engine:id,name'])
            ->orderByRaw('coalesce(sched_start, report_date, created_at)')
            ->get();

        $last = count($months) - 1;
        $unitNames = $units->pluck('name', 'id');

        return [
            'kpis' => [
                DashboardSupport::kpi('wo', 'Work Order', $woCount[$last], null, 'number', 'up', $woCount[$last - 1], $woCount),
                DashboardSupport::kpi('wo_closed', 'WO Selesai', $closedPercent[$last], '%', 'decimal', 'up', $closedPercent[$last - 1], $closedPercent),
                DashboardSupport::kpi('sr_open', 'SR Terbuka', $srOpen[$last], null, 'number', 'down', $srOpen[$last - 1], $srOpen),
                DashboardSupport::kpi('cost', 'Biaya Pemeliharaan', $cost[$last], null, 'rupiah', 'down', $cost[$last - 1], $cost),
                DashboardSupport::kpi('backlog', 'Backlog WO', $backlog->count(), null, 'number', 'down'),
            ],
            'trend' => [
                'labels' => array_column($months, 'label'),
                'series' => [
                    ['name' => 'Work Order', 'values' => $woCount],
                    ['name' => 'Service Request', 'values' => $srCount],
                ],
            ],
            'cost' => [
                'labels' => array_column($months, 'label'),
                'series' => [
                    ['name' => 'Jasa', 'values' => $jasa],
                    ['name' => 'Material', 'values' => $material],
                ],
            ],
            'by_type' => $thisMonth
                ->groupBy(fn (WorkOrder $wo): string => $wo->maintenanceType?->name ?? 'Tanpa jenis')
                ->map(fn ($group, string $label): array => ['label' => $label, 'value' => $group->count()])
                ->sortByDesc('value')->values()->all(),
            'by_status' => $thisMonth
                ->groupBy(fn (WorkOrder $wo): string => $wo->status?->name ?? 'Tanpa status')
                ->map(fn ($group, string $label): array => ['label' => $label, 'value' => $group->count()])
                ->sortByDesc('value')->values()->all(),
            'backlog' => $backlog->take(8)->map(function (WorkOrder $wo) use ($now, $unitNames): array {
                $since = $wo->sched_start ?? $wo->report_date ?? $wo->created_at;

                return [
                    'code' => $wo->wonum,
                    'unit' => $unitNames[$wo->unit_id] ?? '—',
                    'asset' => $wo->engine?->name ?? ($wo->assetnum ?: '—'),
                    'task' => (string) $wo->description,
                    'priority' => $wo->priority_text ?: '—',
                    'priority_tone' => $this->priorityTone($wo->priority_text),
                    'status' => $wo->status?->name ?? '—',
                    'age' => $since === null ? 0 : max(0, (int) $since->copy()->startOfDay()->diffInDays($now->copy()->startOfDay())),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Rows of the visible units whose report period falls in the window.
     *
     * @template TModel of WorkOrder|ServiceRequest
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>  $unitIds
     * @return Builder<TModel>
     */
    private function inWindow(Builder $query, array $unitIds, int $month, int $year): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->join('report_periods', 'report_periods.id', '=', "{$table}.report_period_id")
            ->whereIn("{$table}.unit_id", $unitIds)
            ->where(fn (Builder $q) => $q->where('report_periods.year', '>', $year)->orWhere(fn (Builder $q2) => $q2->where('report_periods.year', $year)->where('report_periods.month', '>=', $month)));
    }

    private function priorityTone(?string $priority): string
    {
        $text = strtolower((string) $priority);

        return match (true) {
            $text === '' => 'neutral',
            str_contains($text, 'tinggi') || str_contains($text, 'high') || str_contains($text, 'urgent') || $text === '1' => 'danger',
            str_contains($text, 'sedang') || str_contains($text, 'medium') || $text === '2' => 'warning',
            default => 'info',
        };
    }
}
