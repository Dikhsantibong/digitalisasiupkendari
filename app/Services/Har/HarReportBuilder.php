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
 * Assembles the HAR monthly report, reading Work Orders / Service Requests
 * only through the {@see WorkOrderSource} interface (so WPC can replace
 * the source later).
 */
class HarReportBuilder
{
    public function __construct(private readonly WorkOrderSource $source) {}

    /**
     * @return array<string, mixed>
     */
    public function monthly(Unit $unit, int $month, int $year): array
    {
        $unit->loadMissing(['serviceUnit:id,name', 'machines' => fn ($q) => $q->where('is_active', true)->orderBy('name')]);
        $workOrders = $this->source->workOrders($unit, $month, $year);
        $serviceRequests = $this->source->serviceRequests($unit, $month, $year);

        return [
            'unit' => ['name' => $unit->name, 'service_unit' => $unit->serviceUnit?->name],
            'period' => ['month' => $month, 'year' => $year, 'label' => Indonesian::monthName($month).' '.$year],
            'machines' => $unit->machines->pluck('name')->all(),
            'sr_summary' => $this->srSummary($serviceRequests, $unit),
            'maintenance_summary' => $this->maintenanceSummary($workOrders, $unit, $month, $year),
            'wo_summary' => $this->woSummary($workOrders),
            'rekap_task_wo' => $this->rekapTaskWo($workOrders),
            'wo_by_type' => $this->woByType($workOrders),
            'wo_waiting' => $this->woWaiting($workOrders),
            'cost' => $this->cost($unit->id, $month, $year),
            'schedules' => $this->schedules($unit->id, $month, $year),
            'activities' => $this->activities($unit->id, $month, $year),
            'attachments' => $this->attachments($unit->id, $month, $year),
        ];
    }

    /**
     * @param  Collection<int, ServiceRequest>  $srs
     * @return array<string, mixed>
     */
    private function srSummary($srs, Unit $unit): array
    {
        $byCategory = $srs->groupBy(fn ($sr): string => $sr->category?->code ?? 'Lainnya')
            ->map(fn ($group, string $code): array => ['category' => $code, 'count' => $group->count()])
            ->values()->all();

        $open = $srs->filter(fn ($sr): bool => $sr->status->value === 'open')->count();

        $byEngine = $srs->groupBy(fn ($sr): string => $sr->engine?->name ?? 'Common')
            ->map(fn ($group, string $engine): array => [
                'engine' => $engine,
                'total' => $group->count(),
                'terbit' => $group->filter(fn ($sr) => strtoupper($sr->category?->code ?? '') !== 'CANCEL')->count(),
                'cancel' => $group->filter(fn ($sr) => strtoupper($sr->category?->code ?? '') === 'CANCEL')->count(),
                'flm' => $group->filter(fn ($sr) => strtoupper($sr->category?->code ?? '') === 'FLM')->count(),
                'cm' => $group->filter(fn ($sr) => strtoupper($sr->category?->code ?? '') === 'CM')->count(),
                'pdm' => $group->filter(fn ($sr) => strtoupper($sr->category?->code ?? '') === 'PDM')->count(),
            ])
            ->values()->all();

        $topAssets = $srs->groupBy(fn ($sr) => $sr->description ?: ($sr->sr_number ?: 'Asset'))
            ->map(fn ($group, $desc) => [
                'asset' => $group->first()->sr_number ?? '—',
                'freq' => $group->count(),
                'description' => $desc,
            ])
            ->sortByDesc('freq')
            ->take(5)
            ->values()
            ->all();

        return [
            'total' => $srs->count(),
            'open' => $open,
            'close' => $srs->count() - $open,
            'by_category' => $byCategory,
            'by_engine' => $byEngine,
            'top_assets' => $topAssets,
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
                    'type' => $wo->maintenanceType?->code,
                    'engine' => $wo->engine?->name,
                    'work_group' => $wo->workGroup?->code,
                    'status' => $wo->status?->code,
                    'cycle' => $wo->cycle?->code,
                    'report_date' => $wo->report_date?->format('Y-m-d'),
                    'sched_start' => $wo->sched_start?->format('Y-m-d'),
                    'sched_finish' => $wo->sched_finish?->format('Y-m-d'),
                    'waiting' => $wo->waiting_reason?->label(),
                    'service_cost' => (float) $wo->service_cost,
                    'material_cost' => (float) $wo->material_cost,
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * @param  Collection<int, WorkOrder>  $wos
     * @return list<array{key: string, reason: string, rows: list<array<string, mixed>>}>
     */
    private function woWaiting($wos): array
    {
        return $wos->filter(fn (WorkOrder $wo): bool => $wo->waiting_reason !== null)
            ->groupBy(fn (WorkOrder $wo): string => $wo->waiting_reason->value)
            ->map(fn ($group, string $reason): array => [
                'key' => $reason,
                'reason' => WoWaitingReason::from($reason)->label(),
                'rows' => $group->map(fn (WorkOrder $wo): array => [
                    'wonum' => $wo->wonum,
                    'description' => $wo->description,
                    'status' => $wo->status?->code,
                    'engine' => $wo->engine?->name,
                    'report_date' => $wo->report_date?->format('Y-m-d'),
                    'work_group' => $wo->workGroup?->code,
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

    /**
     * @param  Collection<int, WorkOrder>  $wos
     * @return array<string, mixed>
     */
    private function maintenanceSummary(Collection $wos, Unit $unit, int $month, int $year): array
    {
        return [
            'rekap_terbit_complete' => $this->rekapTerbitComplete($unit, $year),
            'rekap_status' => $this->rekapStatus($unit, $wos),
            'tasks' => $this->tasksBreakdown($wos),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rekapTerbitComplete(Unit $unit, int $year): array
    {
        $allWos = WorkOrder::query()
            ->where('unit_id', $unit->id)
            ->where(function ($q) use ($year) {
                $q->whereYear('report_date', '<=', $year)
                    ->orWhereNull('report_date');
            })
            ->with(['status:id,code,is_closed'])
            ->get();

        if ($allWos->isEmpty()) {
            return [
                'url' => '192.168.3.85/wpc-ditgas',
                'rows' => [],
            ];
        }

        $periods = [];
        $priorYearLabel = (string) ($year - 1);
        $priorWos = $allWos->filter(fn ($w) => $w->report_date && $w->report_date->year < $year);
        if ($priorWos->isNotEmpty()) {
            $periods[$priorYearLabel] = $priorWos;
        }

        for ($m = 1; $m <= 12; $m++) {
            $mKey = sprintf('%04d%02d', $year, $m);
            $mWos = $allWos->filter(fn ($w) => $w->report_date && $w->report_date->year == $year && $w->report_date->month == $m);
            if ($mWos->isNotEmpty()) {
                $periods[$mKey] = $mWos;
            }
        }

        $rows = [];
        foreach ($periods as $label => $group) {
            $terbit = $group->count();
            $complete = [];
            $totalComplete = 0;
            for ($c = 1; $c <= 12; $c++) {
                $cCount = $group->filter(function ($w) use ($year, $c) {
                    if ($w->actual_finish && $w->actual_finish->year == $year && $w->actual_finish->month == $c) {
                        return true;
                    }

                    return false;
                })->count();
                $complete[$c] = $cCount;
                $totalComplete += $cCount;
            }
            $open = max(0, $terbit - $totalComplete);
            $rows[] = [
                'bulan' => $label,
                'terbit' => $terbit,
                'complete' => $complete,
                'open' => $open,
            ];
        }

        return [
            'url' => '192.168.3.85/wpc-ditgas',
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, WorkOrder>  $wos
     * @return array<string, mixed>
     */
    private function rekapStatus(Unit $unit, Collection $wos): array
    {
        $types = ['CM', 'EM', 'WR', 'RTF', 'PM', 'PDM', 'EJ', 'PAM', 'CP', 'OH', 'ADM', 'OP', 'KOSONG'];
        $statuses = ['APPR', 'CLOSE', 'WAPPR', 'WMATL', 'WPROC', 'WSCH'];

        if ($wos->isEmpty()) {
            return [
                'url' => '192.168.3.85/wpc-ditgas',
                'columns' => $types,
                'rows' => [],
                'totals' => array_fill_keys($types, 0),
                'grand_total' => 0,
            ];
        }

        $rows = [];
        $totals = array_fill_keys($types, 0);
        $grandTotal = 0;

        foreach ($statuses as $statusCode) {
            $forStatus = $wos->filter(fn (WorkOrder $w) => strtoupper($w->status?->code ?? '') === $statusCode);
            $values = [];
            $rowTotal = 0;

            foreach ($types as $typeCode) {
                if ($typeCode === 'KOSONG') {
                    $c = $forStatus->filter(fn (WorkOrder $w) => empty($w->maintenanceType?->code))->count();
                } else {
                    $c = $forStatus->filter(fn (WorkOrder $w) => strtoupper($w->maintenanceType?->code ?? '') === $typeCode)->count();
                }
                $values[$typeCode] = $c;
                $rowTotal += $c;
                $totals[$typeCode] += $c;
            }

            $rows[] = [
                'status' => $statusCode,
                'values' => $values,
                'total' => $rowTotal,
            ];
            $grandTotal += $rowTotal;
        }

        return [
            'url' => '192.168.3.85/wpc-ditgas',
            'columns' => $types,
            'rows' => $rows,
            'totals' => $totals,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param  Collection<int, WorkOrder>  $wos
     * @return array<string, mixed>
     */
    private function tasksBreakdown(Collection $wos): array
    {
        $definitions = [
            1 => ['name' => 'Preventive Maintenance', 'codes' => ['PM']],
            2 => ['name' => 'Proactive Maintenance', 'codes' => ['PAM', 'PaM']],
            3 => ['name' => 'Predictive Maintenance', 'codes' => ['PDM', 'PdM']],
            4 => ['name' => 'Enjiniring / Modifikasi', 'codes' => ['EJ', 'ENJI', 'MODIF']],
            5 => ['name' => 'Run to Failure Maintenance', 'codes' => ['RTF']],
            6 => ['name' => 'Corrective Maintenance', 'codes' => ['CM']],
            7 => ['name' => 'Emergency Maintenance', 'codes' => ['EM', 'EMERGENCY']],
            8 => ['name' => 'Overhaul', 'codes' => ['OH', 'OVERHAUL']],
        ];

        $totalRencana = $wos->count();
        $closedWos = $wos->filter(fn (WorkOrder $w) => (bool) ($w->status?->is_closed ?? false));
        $totalRealisasi = $closedWos->count();

        $rows = [];
        $totalMat = 0.0;
        $totalSvc = 0.0;

        foreach ($definitions as $no => $d) {
            $codesUpper = array_map('strtoupper', $d['codes']);
            $plannedForType = $wos->filter(fn (WorkOrder $w) => in_array(strtoupper($w->maintenanceType?->code ?? ''), $codesUpper, true));
            $closedForType = $closedWos->filter(fn (WorkOrder $w) => in_array(strtoupper($w->maintenanceType?->code ?? ''), $codesUpper, true));

            $rFreq = $plannedForType->count();
            $rPct = $totalRencana > 0 ? round(($rFreq / $totalRencana) * 100, 1) : 0.0;

            $aFreq = $closedForType->count();
            $aPct = $totalRealisasi > 0 ? round(($aFreq / $totalRealisasi) * 100, 1) : 0.0;

            $matCost = (float) $closedForType->sum('material_cost');
            $svcCost = (float) $closedForType->sum('service_cost');

            $totalMat += $matCost;
            $totalSvc += $svcCost;

            $rows[] = [
                'no' => $no,
                'name' => $d['name'],
                'code' => $d['codes'][0],
                'rencana_freq' => $rFreq,
                'rencana_pct' => $rPct,
                'realisasi_freq' => $aFreq,
                'realisasi_pct' => $aPct,
                'keterangan' => '',
                'material_cost' => $matCost,
                'service_cost' => $svcCost,
            ];
        }

        $colors = ['#5b9bd5', '#9e480e', '#255e91', '#70ad47', '#4472c4', '#ed7d31', '#a5a5a5', '#ffc000'];
        $mix = [];
        foreach ($rows as $idx => $r) {
            $mix[] = [
                'label' => $r['name'],
                'pct' => $r['realisasi_pct'],
                'freq' => $r['realisasi_freq'],
                'color' => $colors[$idx % count($colors)],
            ];
        }

        return [
            'rows' => $rows,
            'total_rencana_freq' => $totalRencana,
            'total_rencana_pct' => $totalRencana > 0 ? 100.0 : 0.0,
            'total_realisasi_freq' => $totalRealisasi,
            'total_realisasi_pct' => $totalRealisasi > 0 ? 100.0 : 0.0,
            'total_material_cost' => $totalMat,
            'total_service_cost' => $totalSvc,
            'total_cost' => $totalMat + $totalSvc,
            'mix' => $mix,
        ];
    }

    /**
     * Rekapitulasi WO Task (Preventive, Proactive, Predictive, Corrective, Emergency, ECP)
     * Format Standar PLN NP FMKD-314-10.3.3-A11.
     *
     * @param  Collection<int, WorkOrder>  $wos
     * @return array<string, mixed>
     */
    private function rekapTaskWo(Collection $wos): array
    {
        $disciplinesDef = [
            'listrik' => '- Har Listrik',
            'ic' => '- Har I&C',
            'mekanik_1' => '- Har Mekanik 1',
            'mekanik_2' => '- Har Mekanik 2',
            'civil' => '- Har Civil',
        ];

        $categoriesDef = [
            'I' => ['title' => 'Jumlah Task Proactive', 'codes' => ['PAM', 'PaM']],
            'II' => ['title' => 'Jumlah Task Preventive', 'codes' => ['PM']],
            'III' => ['title' => 'Jumlah Task Predictive', 'codes' => ['PDM', 'PdM']],
            'IV' => ['title' => 'Jumlah Task Corrective', 'codes' => ['CM']],
            'V' => ['title' => 'Jumlah Task Emergency', 'codes' => ['EM', 'EMERGENCY']],
            'VI' => ['title' => 'Jumlah Task ECP', 'codes' => ['ECP', 'EJ', 'ENJI', 'MODIF', 'OH', 'OVERHAUL']],
        ];

        $matchDiscipline = function (WorkOrder $w, string $discKey): bool {
            $code = strtoupper($w->workGroup?->code ?? '');
            $name = strtoupper($w->workGroup?->name ?? '');
            $combined = $code.' '.$name;

            return match ($discKey) {
                'listrik' => str_contains($combined, 'ELEC') || str_contains($combined, 'LISTRIK'),
                'ic' => str_contains($combined, 'I&C') || str_contains($combined, 'INSTRUM') || str_contains($combined, 'IC'),
                'mekanik_1' => (str_contains($combined, 'MECH') || str_contains($combined, 'MEK') || (!str_contains($combined, 'ELEC') && !str_contains($combined, 'LISTRIK') && !str_contains($combined, 'CIVIL') && !str_contains($combined, 'SIPIL') && !str_contains($combined, 'I&C'))) && !str_contains($combined, '2'),
                'mekanik_2' => (str_contains($combined, 'MECH') || str_contains($combined, 'MEK')) && str_contains($combined, '2'),
                'civil' => str_contains($combined, 'CIVIL') || str_contains($combined, 'SIPIL'),
                default => false,
            };
        };

        $categories = [];
        $totalRencanaFreq = 0;
        $totalRealisasiFreq = 0;

        foreach ($categoriesDef as $roman => $cDef) {
            $codesUpper = array_map('strtoupper', $cDef['codes']);
            $catWos = $wos->filter(fn (WorkOrder $w) => in_array(strtoupper($w->maintenanceType?->code ?? ''), $codesUpper, true));
            $catClosed = $catWos->filter(fn (WorkOrder $w) => (bool) ($w->status?->is_closed ?? false));

            $catRFreq = $catWos->count();
            $catAFreq = $catClosed->count();

            $totalRencanaFreq += $catRFreq;
            $totalRealisasiFreq += $catAFreq;

            $disciplines = [];
            foreach ($disciplinesDef as $dKey => $dName) {
                $dPlanned = $catWos->filter(fn (WorkOrder $w) => $matchDiscipline($w, $dKey));
                $dClosed = $catClosed->filter(fn (WorkOrder $w) => $matchDiscipline($w, $dKey));

                $dRFreq = $dPlanned->count();
                $dAFreq = $dClosed->count();

                $dRPct = $catRFreq > 0 ? round(($dRFreq / $catRFreq) * 100, 1) : 0.0;
                $dAPct = $dRFreq > 0 ? round(($dAFreq / $dRFreq) * 100, 1) : 0.0;

                $disciplines[] = [
                    'name' => $dName,
                    'rencana_freq' => $dRFreq,
                    'rencana_pct' => $dRPct,
                    'realisasi_freq' => $dAFreq,
                    'realisasi_pct' => $dAPct,
                ];
            }

            $catRPct = $catRFreq > 0 ? 100.0 : 0.0;
            $catAPct = $catRFreq > 0 ? round(($catAFreq / $catRFreq) * 100, 1) : 0.0;

            $categories[] = [
                'no' => $roman.'.',
                'title' => $cDef['title'],
                'rencana_freq' => $catRFreq,
                'rencana_pct' => $catRPct,
                'realisasi_freq' => $catAFreq,
                'realisasi_pct' => $catAPct,
                'disciplines' => $disciplines,
            ];
        }

        $totalRPct = $totalRencanaFreq > 0 ? 100.0 : 0.0;
        $totalAPct = $totalRencanaFreq > 0 ? round(($totalRealisasiFreq / $totalRencanaFreq) * 100, 1) : 0.0;

        return [
            'categories' => $categories,
            'total_rencana_freq' => $totalRencanaFreq,
            'total_rencana_pct' => $totalRPct,
            'total_realisasi_freq' => $totalRealisasiFreq,
            'total_realisasi_pct' => $totalAPct,
        ];
    }
}

