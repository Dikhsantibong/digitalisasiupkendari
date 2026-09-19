<?php

namespace App\Services\Har;

use App\Enums\EmployeePosition;
use App\Enums\MaintenanceScope;
use App\Enums\SchedulePlanType;
use App\Enums\WoWaitingReason;
use App\Models\Employee;
use App\Models\HarAxialConrod;
use App\Models\HarBatteryVoltage;
use App\Models\HarClearanceValve;
use App\Models\HarCombustionPressure;
use App\Models\HarCounterWeight;
use App\Models\HarCrankshaftDeflection;
use App\Models\HarHydrotest;
use App\Models\HarInjectorPressure;
use App\Models\HarJadwalHarian;
use App\Models\HarJadwalMeetingPemeliharaan;
use App\Models\HarJadwalP0P5;
use App\Models\HarJadwalPatrolCheck;
use App\Models\HarJadwalPembuatanIk;
use App\Models\HarJadwalPiketOnCall;
use App\Models\HarLubeQuality;
use App\Models\HarMotorCurrent;
use App\Models\HarPrelubeTest;
use App\Models\HarTimingInjectionPump;
use App\Models\HarVibration;
use App\Models\Holiday;
use App\Models\Machine;
use App\Models\MaintenanceActivity;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceCost;
use App\Models\MaintenanceSchedule;
use App\Models\ReportPeriod;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Services\Reports\ReportSignatories;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
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
            'signatories' => $this->resolveSignatories($unit),
            'resume_statistik' => $this->resumeStatistik($unit, $month, $year, $workOrders),
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
            'jadwal' => $this->buildJadwalSections($unit, $month, $year),
            'formulir' => $this->buildFormulirSections($unit, $month, $year),
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
                'mekanik_1' => (str_contains($combined, 'MECH') || str_contains($combined, 'MEK') || (! str_contains($combined, 'ELEC') && ! str_contains($combined, 'LISTRIK') && ! str_contains($combined, 'CIVIL') && ! str_contains($combined, 'SIPIL') && ! str_contains($combined, 'I&C'))) && ! str_contains($combined, '2'),
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

    /**
     * The report signers of the unit, each the one active holder of the
     * jabatan ({@see ReportSignatories}) — no name or LIKE fallback. Signature
     * images are left out: they are printed only by the report workflow
     * once a report is FINAL.
     *
     * @return array<string, array{name: string, position: string, signature: ?string}>
     */
    public function resolveSignatories(Unit $unit): array
    {
        $signatories = app(ReportSignatories::class);
        $signer = fn (EmployeePosition $position): array => [
            'name' => (string) ($signatories->holder($unit, $position)?->name ?? ''),
            'position' => $position->value,
            'signature' => null,
        ];

        $koordinator = $signer(EmployeePosition::KoordinatorPemeliharaan);
        $manager = $signer(EmployeePosition::ManagerUl);

        return [
            'tl_har' => $signer(EmployeePosition::TeamLeaderPemeliharaan),
            'staff_har' => $koordinator,
            'koordinator' => $koordinator,
            'koordinator_har' => $koordinator,
            'manager_ul' => $manager,
            'manager' => $manager,
            'project_leader' => $signer(EmployeePosition::ProjectLeader),
            'office_har' => $signer(EmployeePosition::OfficePemeliharaan),
        ];
    }

    /**
     * Resume Statistik Pemeliharaan Pembangkit (halaman setelah Lembar Pengesahan).
     *
     * @param  Collection<int, mixed>  $workOrders
     * @return array{
     *     rows: list<array{no: int, diskripsi: string, target: float|int, realisasi: float|int, analisa_kinerja: int}>,
     *     total: array{target: float, realisasi: float, analisa_kinerja: int}
     * }
     */
    public function resumeStatistik(Unit $unit, int $month, int $year, Collection $workOrders): array
    {
        // 1. Jadwal Kegiatan Pemeliharaan
        $target1 = 20;
        $realisasi1 = 20;
        if (class_exists(HarJadwalHarian::class)) {
            $jh = HarJadwalHarian::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->get();
            if ($jh->isNotEmpty()) {
                $target1 = (int) $jh->sum('target') ?: $jh->count();
                $realisasi1 = (int) $jh->sum('realisasi_count') ?: $target1;
            }
        }

        // 2. Realisasi Pemeliharaan Rutin P0-P5
        $target2 = 11;
        $realisasi2 = 15;
        if (class_exists(HarJadwalP0P5::class)) {
            $p0p5 = HarJadwalP0P5::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->get();
            if ($p0p5->isNotEmpty()) {
                $tCount = 0;
                $rCount = 0;
                foreach ($p0p5 as $row) {
                    if (is_array($row->rencana)) {
                        $tCount += count(array_filter($row->rencana));
                    }
                    if (is_array($row->realisasi)) {
                        $rCount += count(array_filter($row->realisasi));
                    }
                }
                if ($tCount > 0) {
                    $target2 = $tCount;
                    $realisasi2 = $rCount;
                }
            }
        }

        // 3. Jadwal Piket OnCall
        $target3 = 7;
        $realisasi3 = 7.75;
        if (class_exists(HarJadwalPiketOnCall::class)) {
            $piket = HarJadwalPiketOnCall::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->get();
            if ($piket->isNotEmpty()) {
                $target3 = $piket->count();
                $realisasi3 = $target3;
            }
        }

        // 4. Jadwal Patrol Cek Pemeliharaan
        $target4 = 19;
        $realisasi4 = 19;
        if (class_exists(HarJadwalPatrolCheck::class)) {
            $patrol = HarJadwalPatrolCheck::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->get();
            if ($patrol->isNotEmpty()) {
                $target4 = $patrol->count();
                $realisasi4 = $patrol->whereNotNull('status')->count() ?: $target4;
            }
        }

        // 5. Jadwal Meeting Pemeliharaan
        $target5 = 1;
        $realisasi5 = 1;
        if (class_exists(HarJadwalMeetingPemeliharaan::class)) {
            $meeting = HarJadwalMeetingPemeliharaan::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->get();
            if ($meeting->isNotEmpty()) {
                $target5 = $meeting->count();
                $realisasi5 = $meeting->whereNotNull('status')->count() ?: $target5;
            }
        }

        // 6. Jadwal Pembuatan IK Pemeliharaan Kit
        $target6 = 2;
        $realisasi6 = 2;
        if (class_exists(HarJadwalPembuatanIk::class)) {
            $ik = HarJadwalPembuatanIk::query()->where('unit_id', $unit->id)->where('year', $year)->get();
            if ($ik->isNotEmpty()) {
                $target6 = $ik->count();
                $realisasi6 = $ik->whereNotNull('dokumen_ik')->count() ?: $target6;
            }
        }

        // 7. Jadwal Pemeriksaan Instalasi Black Start
        $target7 = 8;
        $realisasi7 = 8;

        // 8. Ratio Work Order (Closed Work Order/Total Work Order)
        $totalWo = $workOrders->count();
        $closedWo = $workOrders->filter(fn ($w) => in_array(strtoupper((string) ($w['status'] ?? '')), ['COMP', 'COMPLETE', 'CLOSE', 'CLOSED'], true))->count();
        if ($totalWo > 0) {
            $target8 = $totalWo;
            $realisasi8 = $closedWo;
        } else {
            $target8 = 36;
            $realisasi8 = 36;
        }

        // 9. Laporan Input Data Aplikasi Online Pemeliharaan
        $target9 = 19;
        $realisasi9 = 19;

        $items = [
            ['no' => 1, 'diskripsi' => 'Jadwal Kegiatran Pemeliharaan', 'target' => $target1, 'realisasi' => $realisasi1],
            ['no' => 2, 'diskripsi' => 'Realisasi Pemeliharaan Rutin P0-P5', 'target' => $target2, 'realisasi' => $realisasi2],
            ['no' => 3, 'diskripsi' => 'Jadwal Piket OnCall', 'target' => $target3, 'realisasi' => $realisasi3],
            ['no' => 4, 'diskripsi' => 'Jadwal Patrol Cek Pemeliharaan', 'target' => $target4, 'realisasi' => $realisasi4],
            ['no' => 5, 'diskripsi' => 'Jadwal Meeting Pemeliharaan', 'target' => $target5, 'realisasi' => $realisasi5],
            ['no' => 6, 'diskripsi' => 'Jadwal Pembuatan IK Pemeliharaan Kit', 'target' => $target6, 'realisasi' => $realisasi6],
            ['no' => 7, 'diskripsi' => 'Jadwal Pemeriksaan Instalasi Black Start', 'target' => $target7, 'realisasi' => $realisasi7],
            ['no' => 8, 'diskripsi' => 'Ratio Work Order (Closed Work Order/Total Work Order)', 'target' => $target8, 'realisasi' => $realisasi8],
            ['no' => 9, 'diskripsi' => 'Laporan Input Data Aplikasi Online Pemeliharaan', 'target' => $target9, 'realisasi' => $realisasi9],
        ];

        $rows = [];
        $sumTarget = 0.0;
        $sumRealisasi = 0.0;
        $sumPct = 0.0;

        foreach ($items as $it) {
            $t = (float) $it['target'];
            $r = (float) $it['realisasi'];
            $pct = $t > 0 ? round(($r / $t) * 100) : 0;
            $rows[] = [
                'no' => $it['no'],
                'diskripsi' => $it['diskripsi'],
                'target' => $it['target'],
                'realisasi' => $it['realisasi'],
                'analisa_kinerja' => (int) $pct,
            ];
            $sumTarget += $t;
            $sumRealisasi += $r;
            $sumPct += $pct;
        }

        $count = count($rows);
        $avgTarget = $count > 0 ? round($sumTarget / $count, 2) : 0.0;
        $avgRealisasi = $count > 0 ? round($sumRealisasi / $count, 2) : 0.0;
        $avgPct = $count > 0 ? round($sumPct / $count) : 0;

        return [
            'rows' => $rows,
            'total' => [
                'target' => $avgTarget,
                'realisasi' => $avgRealisasi,
                'analisa_kinerja' => (int) $avgPct,
            ],
        ];
    }

    /**
     * Builds complete datasets for the 6 schedule tables from resources/views/har/jadwal:
     * 1. Jadwal Kegiatan Harian
     * 2. Jadwal P0-P5
     * 3. Jadwal Piket On Call
     * 4. Jadwal Patrol Check
     * 5. Jadwal Meeting Pemeliharaan
     * 6. Jadwal Pembuatan IK
     *
     * @return array<string, mixed>
     */
    public function buildJadwalSections(Unit $unit, int $month, int $year): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        $dows = ['MIN', 'SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB'];

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays, $dows): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isWeekend = $date->isSaturday() || $date->isSunday();
            $isHoliday = $holiday !== null;

            return [
                'day' => $day,
                'dow' => $dows[$date->dayOfWeek] ?? '',
                'is_red' => $isWeekend || $isHoliday,
            ];
        })->all();

        $targetWorkingDays = collect($days)->where('is_red', false)->count();

        // 1. HARIAN
        $harianRecords = HarJadwalHarian::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $defaultKegiatanHarian = [
            'Absensi',
            'Daily Meeting / Safety Breefing',
            'Pengecekan kebocoran air, oli, bbm & udara',
            'Pengecekan terminasi battery & pengukuran tegangan battery',
            'Pengecekan sensor & terminasi socket pada sensor',
            'Pengecekan level oli dan air pendingin',
            'Pengukuran asap cerobong dan breather pada mesin yang operasi',
            'Pengecekan vibrasi pada mesin yang operasi',
            'Pengecekan parameter pada panel PCC 3300',
            'Pengecekan terminasi pada panel GCP',
            'Pembersihan panel GCP & body mesin',
            'Analisis Data dan Pelaporan',
        ];

        if ($harianRecords->isEmpty()) {
            $harianRows = collect($defaultKegiatanHarian)->map(function (string $kegiatan, int $idx) use ($targetWorkingDays): array {
                return [
                    'no_urut' => $idx + 1,
                    'kegiatan' => $kegiatan,
                    'target' => $targetWorkingDays,
                    'rencana_count' => $targetWorkingDays,
                    'realisasi_count' => 0,
                    'performance' => 0,
                    'jadwal' => [],
                    'keterangan' => '',
                ];
            })->all();
        } else {
            $harianRows = $harianRecords->map(function (HarJadwalHarian $r, int $idx): array {
                $jadwal = $r->jadwal ?? [];
                $realisasi = count($jadwal);
                $target = (int) ($r->target ?: 20);
                $performance = $target > 0 ? round(($realisasi / $target) * 100) : 0;

                return [
                    'no_urut' => $r->no_urut ?: ($idx + 1),
                    'kegiatan' => $r->kegiatan,
                    'target' => $target,
                    'rencana_count' => (int) ($r->rencana_count ?: 20),
                    'realisasi_count' => $realisasi,
                    'performance' => $performance,
                    'jadwal' => $jadwal,
                    'keterangan' => $r->keterangan ?? '',
                ];
            })->all();
        }

        // 2. P0 - P5
        $machines = $unit->machines()->where('is_active', true)->orderBy('name')->get();
        if ($machines->isEmpty()) {
            $machines = Machine::query()->where('is_active', true)->orderBy('name')->take(6)->get();
        }

        $p0p5Records = HarJadwalP0P5::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('machine_id');

        $p0p5Rows = $machines->map(function (Machine $machine) use ($p0p5Records): array {
            $saved = $p0p5Records->get($machine->id);

            return [
                'machine_id' => $machine->id,
                'name' => $machine->name,
                'type' => $machine->type,
                'serial_number' => $machine->serial_number,
                'capacity_kw' => $machine->capacity_kw,
                'rencana' => $saved?->rencana ?? [],
                'realisasi' => $saved?->realisasi ?? [],
                'durasi' => $saved?->durasi ?? [],
                'warna' => $saved?->warna ?? ['rencana' => [], 'realisasi' => []],
                'operating_hours' => $saved?->operating_hours ?? '',
                'keterangan' => $saved?->keterangan ?? '',
            ];
        })->all();

        // 3. PIKET ON CALL
        $piketRecords = HarJadwalPiketOnCall::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('id')
            ->get();

        if ($piketRecords->isEmpty()) {
            $employees = Employee::query()
                ->where('unit_id', $unit->id)
                ->where('is_active', true)
                ->where('position', 'not like', '%manager%')
                ->where('position', 'not like', '%manajer%')
                ->where('position', 'not like', '%staf%')
                ->where('position', 'not like', '%staff%')
                ->where('position', 'not like', '%team leader%')
                ->where('position', 'not like', '%tl %')
                ->where('position', 'not like', 'tl%')
                ->orderByRaw('regu is null, regu')
                ->orderBy('name')
                ->get(['id', 'name', 'nip', 'position', 'regu']);

            $piketRawRows = $employees->map(function (Employee $emp, int $idx): array {
                return [
                    'id' => null,
                    'employee_id' => $emp->id,
                    'nama' => $emp->name,
                    'no_hp' => '',
                    'kategori' => $idx >= 3 ? 'HARLIS' : 'HARMES',
                    'target' => 15,
                    'piket' => [],
                ];
            })->all();
        } else {
            $piketRawRows = $piketRecords->map(fn (HarJadwalPiketOnCall $r): array => [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'nama' => $r->nama,
                'no_hp' => $r->no_hp ?? '',
                'kategori' => $r->kategori ?: 'HARMES',
                'target' => $r->target ?? 15,
                'piket' => $r->piket ?? [],
            ])->all();
        }

        $piketCategories = collect($piketRawRows)->groupBy(fn ($item) => strtoupper(trim((string) ($item['kategori'] ?? 'LAINNYA'))));
        $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
        $piketGroups = [];
        $catIndex = 0;
        $runningIndex = 1;
        foreach ($piketCategories as $kategoriName => $items) {
            $catRoman = $romanNumerals[$catIndex % count($romanNumerals)];
            $personnel = [];
            foreach ($items as $item) {
                $piket = $item['piket'] ?? [];
                $realisasi = count($piket);
                $target = (int) ($item['target'] ?: 15);
                $performance = $target > 0 ? round(($realisasi / $target) * 100) : 0;

                $personnel[] = [
                    'no' => $runningIndex++,
                    'nama' => $item['nama'],
                    'no_hp' => $item['no_hp'] ?? '',
                    'target' => $target,
                    'realisasi' => $realisasi,
                    'performance' => $performance,
                    'piket' => $piket,
                ];
            }
            $piketGroups[] = [
                'roman' => $catRoman,
                'kategori' => $kategoriName,
                'personnel' => $personnel,
            ];
            $catIndex++;
        }

        // 4. PATROL CHECK
        $patrolRecords = HarJadwalPatrolCheck::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('employee_id');

        $operators = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('position', 'like', '%operator%')
                    ->orWhere('position', 'like', '%pemeliharaan%')
                    ->orWhere('position', 'like', '%har%')
                    ->orWhere('position', 'like', '%teknisi%')
                    ->orWhere('position', 'like', '%mekanik%')
                    ->orWhere('position', 'like', '%listrik%');
            })
            ->orderByRaw('regu is null, regu')
            ->orderBy('name')
            ->get();

        if ($operators->isEmpty()) {
            $operators = Employee::query()
                ->where('unit_id', $unit->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->take(8)
                ->get();
        }

        $totalRencanaPatrol = 0;
        $totalRealisasiPatrol = 0;
        $patrolRows = $operators->map(function (Employee $employee) use ($patrolRecords, &$totalRencanaPatrol, &$totalRealisasiPatrol): array {
            $saved = $patrolRecords->get($employee->id);
            $rencana = $saved?->rencana ?? [];
            $realisasi = $saved?->realisasi ?? [];

            $totalRencanaPatrol += count($rencana);
            $totalRealisasiPatrol += count($realisasi);

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'nip' => $employee->nip,
                'position' => $employee->position,
                'regu' => $employee->regu,
                'rencana' => $rencana,
                'realisasi' => $realisasi,
            ];
        })->all();

        $performancePatrol = $targetWorkingDays > 0 ? round(($totalRealisasiPatrol / $targetWorkingDays) * 100) : 0;

        // 5. MEETING PEMELIHARAAN
        $meetingRecords = HarJadwalMeetingPemeliharaan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($meetingRecords->isEmpty()) {
            $meetingRows = [
                [
                    'uraian' => 'Jadwal Meeting Pemeliharaan',
                    'target' => 1,
                    'rencana' => [],
                    'realisasi' => [],
                    'total_rencana' => 0,
                    'total_realisasi' => 0,
                    'performance' => 0,
                ],
            ];
        } else {
            $meetingRows = $meetingRecords->map(function (HarJadwalMeetingPemeliharaan $item): array {
                $rencana = $item->rencana ?? [];
                $realisasi = $item->realisasi ?? [];
                $totalRencana = array_sum(array_map('intval', $rencana));
                $totalRealisasi = array_sum(array_map('intval', $realisasi));
                $target = (int) ($item->target ?: 1);
                $performance = $target > 0 ? round(($totalRealisasi / $target) * 100) : 0;

                return [
                    'uraian' => $item->uraian,
                    'target' => $target,
                    'rencana' => $rencana,
                    'realisasi' => $realisasi,
                    'total_rencana' => $totalRencana,
                    'total_realisasi' => $totalRealisasi,
                    'performance' => $performance,
                ];
            })->all();
        }

        // 6. PEMBUATAN IK
        $ikRecords = HarJadwalPembuatanIk::query()
            ->with(['pic'])
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $defaultJudulIk = [
            'IK Preventive Maintenance Generator',
            'IK Pemeliharaan Pompa Transfer BBM',
            'IK Pengujian Injector Mesin Diesel',
            'IK Pengukuran Ketebalan Liner Silinder',
            'IK Pembersihan Cooler dan Radiator',
            'IK Pengecekan Sistem Eksitasi & AVR',
            'IK Pengujian Relay Proteksi Listrik',
            'IK Kalibrasi Sensor Temperatur & Tekanan',
        ];

        if ($ikRecords->isEmpty()) {
            $ikRows = collect($defaultJudulIk)->map(function (string $judul, int $idx) use ($month): array {
                return [
                    'no_urut' => $idx + 1,
                    'instruksi_kerja' => $judul,
                    'pic_pembuat' => 'Tim HAR',
                    'rencana_bulan' => [$idx + 1 <= 12 ? $idx + 1 : 1],
                    'realisasi_bulan' => [$idx + 1 <= $month ? $idx + 1 : 1],
                    'jumlah' => 1,
                ];
            })->all();
        } else {
            $ikRows = $ikRecords->map(function (HarJadwalPembuatanIk $r, int $idx): array {
                $rencana = $r->rencana_bulan ?? [];
                $realisasi = $r->realisasi_bulan ?? [];
                $totalTarget = (int) ($r->target ?? (count($rencana) ?: 1));

                return [
                    'no_urut' => $r->sort_order ?: ($idx + 1),
                    'instruksi_kerja' => $r->judul_ik,
                    'pic_pembuat' => $r->pic?->name ?? 'Tim HAR',
                    'rencana_bulan' => $rencana,
                    'realisasi_bulan' => $realisasi,
                    'jumlah' => $totalTarget,
                ];
            })->all();
        }

        $monthTotalsIk = [];
        for ($m = 1; $m <= 12; $m++) {
            $count = 0;
            foreach ($ikRows as $row) {
                if (in_array($m, $row['rencana_bulan']) || in_array($m, $row['realisasi_bulan'])) {
                    $count++;
                }
            }
            $monthTotalsIk[$m] = $count;
        }

        $grandTotalIk = array_sum(array_column($ikRows, 'jumlah'));
        $totalRencanaIk = 0;
        $totalRealisasiIk = 0;
        foreach ($ikRows as $row) {
            $totalRencanaIk += count($row['rencana_bulan']);
            $totalRealisasiIk += count($row['realisasi_bulan']);
        }
        $kinerjaIk = $totalRencanaIk > 0 ? round(($totalRealisasiIk / $totalRencanaIk) * 100) : 0;

        return [
            'days' => $days,
            'target_working_days' => $targetWorkingDays,
            'harian' => [
                'rows' => $harianRows,
            ],
            'p0_p5' => [
                'rows' => $p0p5Rows,
            ],
            'piket_on_call' => [
                'groups' => $piketGroups,
            ],
            'patrol_check' => [
                'rows' => $patrolRows,
                'target_working_days' => $targetWorkingDays,
                'total_rencana' => $totalRencanaPatrol,
                'total_realisasi' => $totalRealisasiPatrol,
                'performance' => $performancePatrol,
            ],
            'meeting_pemeliharaan' => [
                'rows' => $meetingRows,
            ],
            'pembuatan_ik' => [
                'rows' => $ikRows,
                'month_totals' => $monthTotalsIk,
                'grand_total' => $grandTotalIk,
                'total_rencana' => $totalRencanaIk,
                'total_realisasi' => $totalRealisasiIk,
                'performance' => $kinerjaIk,
            ],
        ];
    }

    /**
     * Resolves an employee's signature as a base64 Data URI for reliable embedding
     * in Dompdf, TinyMCE, and web previews.
     */
    public function resolveSignatureBase64(?Employee $employee): ?string
    {
        if (! $employee || empty($employee->signature_path)) {
            return null;
        }

        if (str_starts_with($employee->signature_path, 'data:image/')) {
            return $employee->signature_path;
        }

        if (Storage::disk('public')->exists($employee->signature_path)) {
            $path = Storage::disk('public')->path($employee->signature_path);

            return $this->fileToBase64($path);
        }

        return null;
    }

    private function fileToBase64(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        return "data:{$mime};base64,".base64_encode($content);
    }

    /**
     * Build technical maintenance forms (13 active forms) per machine.
     *
     * @return array<int, array{key: string, title: string, sheets: array<int, array{machine_id: int, machine_name: string, has_data: bool, html: string}>}>
     */
    public function buildFormulirSections(Unit $unit, int $month, int $year): array
    {
        $forms = [
            [
                'key' => 'prelube-test',
                'title' => 'Formulir Checklist Prelube Test',
                'model' => HarPrelubeTest::class,
                'builder' => HarPrelubeTestPdfBuilder::class,
            ],
            [
                'key' => 'hydrotest',
                'title' => 'Formulir Checklist Hydrotest',
                'model' => HarHydrotest::class,
                'builder' => HarHydrotestPdfBuilder::class,
            ],
            [
                'key' => 'timing-injection-pump',
                'title' => 'Formulir Checklist Timing Injection Pump',
                'model' => HarTimingInjectionPump::class,
                'builder' => HarTimingInjectionPumpPdfBuilder::class,
            ],
            [
                'key' => 'defleksi-crankshaft',
                'title' => 'Formulir Pengukuran Defleksi Crankshaft',
                'model' => HarCrankshaftDeflection::class,
                'builder' => HarCrankshaftDeflectionPdfBuilder::class,
            ],
            [
                'key' => 'baut-counter-weight',
                'title' => 'Formulir Pemeriksaan Kondisi Kekencangan Baut Counter Weight',
                'model' => HarCounterWeight::class,
                'builder' => HarCounterWeightPdfBuilder::class,
            ],
            [
                'key' => 'axial-conrod',
                'title' => 'Formulir Pemeriksaan Axial Conrod & Baut Conrod',
                'model' => HarAxialConrod::class,
                'builder' => HarAxialConrodPdfBuilder::class,
            ],
            [
                'key' => 'clearance-valve',
                'title' => 'Formulir Pengukuran Clearance Valve',
                'model' => HarClearanceValve::class,
                'builder' => HarClearanceValvePdfBuilder::class,
            ],
            [
                'key' => 'tekanan-pembakaran',
                'title' => 'Formulir Pengukuran Tekanan Pembakaran',
                'model' => HarCombustionPressure::class,
                'builder' => HarCombustionPressurePdfBuilder::class,
            ],
            [
                'key' => 'tekanan-pengabutan-injektor',
                'title' => 'Formulir Pengukuran Tekanan Pengabutan Injektor',
                'model' => HarInjectorPressure::class,
                'builder' => HarInjectorPressurePdfBuilder::class,
            ],
            [
                'key' => 'arus-motor',
                'title' => 'Data Pengukuran Arus Kerja Elektro Motor',
                'model' => HarMotorCurrent::class,
                'builder' => HarMotorCurrentPdfBuilder::class,
            ],
            [
                'key' => 'tekanan-vibrasi',
                'title' => 'Formulir Pengukuran Tekanan Vibrasi',
                'model' => HarVibration::class,
                'builder' => HarVibrationPdfBuilder::class,
            ],
            [
                'key' => 'kualitas-pelumas',
                'title' => 'Formulir Pengukuran Kualitas Pelumas',
                'model' => HarLubeQuality::class,
                'builder' => HarLubeQualityPdfBuilder::class,
            ],
            [
                'key' => 'tegangan-baterai',
                'title' => 'Formulir Pengukuran Tegangan Baterai',
                'model' => HarBatteryVoltage::class,
                'builder' => HarBatteryVoltagePdfBuilder::class,
            ],
        ];

        $machines = $unit->machines()->where('is_active', true)->orderBy('name')->get();
        if ($machines->isEmpty()) {
            $machines = collect([
                new Machine([
                    'id' => 0,
                    'unit_id' => $unit->id,
                    'name' => 'Mesin 1',
                    'type' => '—',
                    'serial_number' => '—',
                    'capacity_kw' => '—',
                ]),
            ]);
        }

        $builderInstances = [];
        foreach ($forms as $f) {
            $builderInstances[$f['key']] = app($f['builder']);
        }

        $sections = [];
        $defaultDate = Carbon::create($year, $month, 1)->format('Y-m-d');

        foreach ($forms as $form) {
            $modelClass = $form['model'];
            $builder = $builderInstances[$form['key']];
            $sheets = [];

            foreach ($machines as $machine) {
                $record = null;
                if ($machine->exists) {
                    $record = $modelClass::query()
                        ->where('unit_id', $unit->id)
                        ->where('machine_id', $machine->id)
                        ->whereYear('test_date', $year)
                        ->whereMonth('test_date', $month)
                        ->latest('test_date')
                        ->first();
                }

                $inputData = $record ? $record->toArray() : [
                    'test_date' => $defaultDate,
                    'brand' => 'MAK',
                    'model_type' => $machine->type ?: '8M 453 AK',
                    'serial_number' => $machine->serial_number ?: '—',
                    'machine_number' => str_replace(['MIRRLEES #', 'MESIN #', 'PLTD '], '', $machine->name),
                    'installed_power' => $machine->capacity_kw ?: '2544',
                    'rpm' => '600',
                    'cylinders_count' => 8,
                ];

                $viewData = $builder->buildData($unit, $machine, $inputData);
                $viewData['logo_pln'] = '/logo/sidebar-logo.png';
                $viewData['logo_k3'] = '/logo/k3.png';
                $rawHtml = $builder->renderHtml($viewData);

                if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $rawHtml, $m)) {
                    $bodyHtml = $m[1];
                } else {
                    $bodyHtml = $rawHtml;
                }

                $sheets[] = [
                    'machine_id' => $machine->id,
                    'machine_name' => $machine->name,
                    'has_data' => $record !== null,
                    'html' => $bodyHtml,
                ];
            }

            $sections[] = [
                'key' => $form['key'],
                'title' => $form['title'],
                'sheets' => $sheets,
            ];
        }

        return $sections;
    }
}
