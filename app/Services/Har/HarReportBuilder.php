<?php

namespace App\Services\Har;

use App\Enums\MaintenanceScope;
use App\Enums\SchedulePlanType;
use App\Enums\WoWaitingReason;
use App\Models\MaintenanceActivity;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceCost;
use App\Models\MaintenanceSchedule;
use App\Models\ReportPeriod;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Support\Indonesian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Assembles the HAR monthly report and its executive summary from the same
 * data, reading Work Orders / Service Requests only through the
 * {@see WorkOrderSource} interface (so WPC can replace the source later).
 */
class HarReportBuilder
{
    public function __construct(private readonly WorkOrderSource $source) {}

    /**
     * @return array<string, mixed>
     */
    public function monthly(Unit $unit, int $month, int $year): array
    {
        $unit->loadMissing('serviceUnit:id,name');
        $workOrders = $this->source->workOrders($unit, $month, $year);
        $serviceRequests = $this->source->serviceRequests($unit, $month, $year);

        return [
            'unit' => ['name' => $unit->name, 'service_unit' => $unit->serviceUnit?->name],
            'period' => ['month' => $month, 'year' => $year, 'label' => Indonesian::monthName($month).' '.$year],
            'sr_summary' => $this->srSummary($serviceRequests),
            'wo_summary' => $this->woSummary($workOrders),
            'wo_by_type' => $this->woByType($workOrders),
            'wo_waiting' => $this->woWaiting($workOrders),
            'cost' => $this->cost($unit->id, $month, $year),
            'schedules' => $this->schedules($unit->id, $month, $year),
            'activities' => $this->activities($unit->id, $month, $year),
            'attachments' => $this->attachments($unit->id, $month, $year),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function executive(Unit $unit, int $month, int $year): array
    {
        $report = $this->monthly($unit, $month, $year);

        return [
            'unit' => $report['unit'],
            'period' => $report['period'],
            'sr_total' => $report['sr_summary']['total'],
            'sr_open' => $report['sr_summary']['open'],
            'wo_total' => $report['wo_summary']['total'],
            'wo_complete' => $report['wo_summary']['complete'],
            'wo_percent' => $report['wo_summary']['percent'],
            'wo_by_type' => collect($report['wo_by_type'])->map(fn (array $g): array => [
                'type' => $g['type'],
                'count' => count($g['rows']),
            ])->all(),
            'waiting_count' => collect($report['wo_waiting'])->sum(fn (array $g): int => count($g['rows'])),
            'cost_total' => $report['cost']['effective_total'],
            'cost_ytd' => $report['cost']['ytd'],
            'cost_source' => $report['cost']['source'],
        ];
    }

    /**
     * @param  Collection<int, ServiceRequest>  $srs
     * @return array<string, mixed>
     */
    private function srSummary($srs): array
    {
        $byCategory = $srs->groupBy(fn ($sr): string => $sr->category?->code ?? 'Lainnya')
            ->map(fn ($group, string $code): array => ['category' => $code, 'count' => $group->count()])
            ->values()->all();

        $open = $srs->filter(fn ($sr): bool => $sr->status->value === 'open')->count();

        return [
            'total' => $srs->count(),
            'open' => $open,
            'close' => $srs->count() - $open,
            'by_category' => $byCategory,
        ];
    }

    /**
     * @param  Collection<int, WorkOrder>  $wos
     * @return array<string, mixed>
     */
    private function woSummary($wos): array
    {
        $total = $wos->count();
        $complete = $wos->filter(fn (WorkOrder $wo): bool => (bool) ($wo->status?->is_closed ?? false))->count();

        return [
            'total' => $total,
            'complete' => $complete,
            'open' => $total - $complete,
            'percent' => $total > 0 ? round($complete / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, WorkOrder>  $wos
     * @return list<array{type: string, rows: list<array<string, mixed>>}>
     */
    private function woByType($wos): array
    {
        return $wos->groupBy(fn (WorkOrder $wo): string => $wo->maintenanceType?->code ?? 'Lainnya')
            ->map(fn ($group, string $type): array => [
                'type' => $type,
                'rows' => $group->map(fn (WorkOrder $wo): array => [
                    'wonum' => $wo->wonum,
                    'description' => $wo->description,
                    'report_date' => $wo->report_date?->format('Y-m-d'),
                    'sched_start' => $wo->sched_start?->format('Y-m-d'),
                    'sched_finish' => $wo->sched_finish?->format('Y-m-d'),
                    'status' => $wo->status?->code,
                    'work_group' => $wo->workGroup?->code,
                    'engine' => $wo->engine?->name,
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * @param  Collection<int, WorkOrder>  $wos
     * @return list<array{reason: string, rows: list<array<string, mixed>>}>
     */
    private function woWaiting($wos): array
    {
        return $wos->filter(fn (WorkOrder $wo): bool => $wo->waiting_reason !== null)
            ->groupBy(fn (WorkOrder $wo): string => $wo->waiting_reason->value)
            ->map(fn ($group, string $reason): array => [
                'reason' => WoWaitingReason::from($reason)->label(),
                'rows' => $group->map(fn (WorkOrder $wo): array => [
                    'wonum' => $wo->wonum,
                    'description' => $wo->description,
                    'status' => $wo->status?->code,
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * Plan-vs-realisation matrices grouped by scope (HAR / Pelumas / Air). Each
     * scope lists its machines with the plan and realisation day maps side by side.
     *
     * @return list<array{scope: string, rows: list<array{engine: string, rencana: array<string, mixed>, realisasi: array<string, mixed>}>}>
     */
    private function schedules(int $unitId, int $month, int $year): array
    {
        $schedules = MaintenanceSchedule::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->with('engine:id,name')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        return collect(MaintenanceScope::cases())
            ->map(function (MaintenanceScope $scope) use ($schedules): ?array {
                $forScope = $schedules->where('scope', $scope);

                if ($forScope->isEmpty()) {
                    return null;
                }

                $rows = $forScope->groupBy(fn (MaintenanceSchedule $s): string => $s->engine?->name ?? 'Tanpa mesin')
                    ->map(fn ($group, string $engine): array => [
                        'engine' => $engine,
                        'rencana' => $group->firstWhere('plan_type', SchedulePlanType::Rencana)?->schedule_data ?? [],
                        'realisasi' => $group->firstWhere('plan_type', SchedulePlanType::Realisasi)?->schedule_data ?? [],
                    ])->values()->all();

                return ['scope' => $scope->label(), 'rows' => $rows];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The HARMES activity log with its task lines and materials.
     *
     * @return list<array<string, mixed>>
     */
    private function activities(int $unitId, int $month, int $year): array
    {
        $periodId = $this->periodId($unitId, $month, $year);

        if ($periodId === null) {
            return [];
        }

        return MaintenanceActivity::query()
            ->where('unit_id', $unitId)->where('report_period_id', $periodId)
            ->with(['engine:id,name', 'maintenanceType:id,code', 'workOrder:id,wonum', 'tasks', 'materials'])
            ->orderBy('activity_date')
            ->get()
            ->map(fn (MaintenanceActivity $a): array => [
                'date' => $a->activity_date?->format('Y-m-d'),
                'engine' => $a->engine?->name,
                'type' => $a->maintenanceType?->code,
                'work_result' => $a->work_result,
                'no_wo' => $a->workOrder?->wonum,
                'no_sr' => $a->no_sr,
                'no_lh05' => $a->no_lh05,
                'no_tug9' => $a->no_tug9,
                'keterangan' => $a->keterangan,
                'tasks' => $a->tasks->sortBy('sort_order')->pluck('task_description')->values()->all(),
                'materials' => $a->materials->map(fn ($m): array => [
                    'name' => $m->material_name,
                    'part_number' => $m->part_number,
                    'quantity' => $m->quantity,
                    'unit_of_measure' => $m->unit_of_measure,
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Photo attachments for the period, resolved to public URLs for printing.
     *
     * @return list<array{title: string, caption: string|null, engine: string|null, taken_date: string|null, url: string}>
     */
    private function attachments(int $unitId, int $month, int $year): array
    {
        $periodId = $this->periodId($unitId, $month, $year);

        if ($periodId === null) {
            return [];
        }

        return MaintenanceAttachment::query()
            ->where('unit_id', $unitId)->where('report_period_id', $periodId)
            ->with('engine:id,name')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (MaintenanceAttachment $a): array => [
                'title' => $a->title,
                'caption' => $a->caption,
                'engine' => $a->engine?->name,
                'taken_date' => $a->taken_date?->format('Y-m-d'),
                'url' => Storage::disk('public')->url($a->photo_path),
            ])
            ->all();
    }

    private function periodId(int $unitId, int $month, int $year): ?int
    {
        return ReportPeriod::query()
            ->where('unit_id', $unitId)->where('month', $month)->where('year', $year)->value('id');
    }

    /**
     * @return array{auto_service: float, auto_material: float, auto_total: float, effective_total: float, source: string, ytd: float}
     */
    private function cost(int $unitId, int $month, int $year): array
    {
        [$service, $material] = $this->autoCost($unitId, $month, $year);
        $manual = MaintenanceCost::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->first();

        $useManual = $manual !== null && $manual->use_manual;
        $effective = $useManual
            ? (float) ($manual->service_cost ?? 0) + (float) ($manual->material_cost ?? 0)
            : $service + $material;

        return [
            'auto_service' => $service,
            'auto_material' => $material,
            'auto_total' => $service + $material,
            'effective_total' => round($effective, 2),
            'source' => $useManual ? 'manual' : 'auto',
            'ytd' => $this->yearToDate($unitId, $month, $year),
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function autoCost(int $unitId, int $month, int $year): array
    {
        $periodId = ReportPeriod::query()
            ->where('unit_id', $unitId)->where('month', $month)->where('year', $year)->value('id');

        if ($periodId === null) {
            return [0.0, 0.0];
        }

        $query = WorkOrder::query()->where('unit_id', $unitId)->where('report_period_id', $periodId);

        return [(float) (clone $query)->sum('service_cost'), (float) (clone $query)->sum('material_cost')];
    }

    private function yearToDate(int $unitId, int $month, int $year): float
    {
        $total = 0.0;
        for ($m = 1; $m <= $month; $m++) {
            $manual = MaintenanceCost::query()
                ->where('unit_id', $unitId)->where('year', $year)->where('month', $m)->first();

            if ($manual !== null && $manual->use_manual) {
                $total += (float) ($manual->service_cost ?? 0) + (float) ($manual->material_cost ?? 0);
            } else {
                [$s, $mat] = $this->autoCost($unitId, $m, $year);
                $total += $s + $mat;
            }
        }

        return round($total, 2);
    }
}
