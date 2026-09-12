<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\WorkOrderSource;
use App\Enums\WoWaitingReason;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MaintenanceCycle;
use App\Models\MaintenanceType;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Models\WorkGroup;
use App\Models\WorkOrder;
use App\Models\WoStatus;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Work Order input (modul HAR). An Excel-like grid: foreign keys are typed as
 * codes (maintenance type / work group / status / cycle / engine name) and
 * resolved server-side, so a block can be pasted straight from Excel/WPC.
 */
class WorkOrderController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();

        $orders = $period === null ? collect() : WorkOrder::query()
            ->where('unit_id', $unit->id)
            ->where('report_period_id', $period->id)
            ->with(['engine:id,name', 'maintenanceType:id,code', 'workGroup:id,code', 'status:id,code', 'cycle:id,code'])
            ->orderBy('report_date')
            ->get();

        return Inertia::render('har/input/work-orders', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'rows' => $orders->map(fn (WorkOrder $wo): array => [
                'wonum' => $wo->wonum,
                'description' => $wo->description,
                'type_code' => $wo->maintenanceType?->code,
                'engine_name' => $wo->engine?->name,
                'work_group_code' => $wo->workGroup?->code,
                'status_code' => $wo->status?->code,
                'cycle_code' => $wo->cycle?->code,
                'report_date' => $wo->report_date?->format('Y-m-d'),
                'sched_start' => $wo->sched_start?->format('Y-m-d'),
                'sched_finish' => $wo->sched_finish?->format('Y-m-d'),
                'waiting_reason' => $wo->waiting_reason?->value,
                'service_cost' => $wo->service_cost,
                'material_cost' => $wo->material_cost,
            ])->all(),
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'maintenance_types' => MaintenanceType::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name']),
                'work_groups' => WorkGroup::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name']),
                'statuses' => WoStatus::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name']),
                'cycles' => MaintenanceCycle::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name']),
                'machines' => Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'waiting_reasons' => collect(WoWaitingReason::cases())->map(fn (WoWaitingReason $r): array => ['value' => $r->value, 'label' => $r->label()])->all(),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.wonum' => ['nullable', 'string', 'max:100'],
            'rows.*.description' => ['nullable', 'string', 'max:1000'],
            'rows.*.type_code' => ['nullable', 'string'],
            'rows.*.engine_name' => ['nullable', 'string'],
            'rows.*.work_group_code' => ['nullable', 'string'],
            'rows.*.status_code' => ['nullable', 'string'],
            'rows.*.cycle_code' => ['nullable', 'string'],
            'rows.*.report_date' => ['nullable', 'date'],
            'rows.*.sched_start' => ['nullable', 'date'],
            'rows.*.sched_finish' => ['nullable', 'date'],
            'rows.*.waiting_reason' => ['nullable', 'string'],
            'rows.*.service_cost' => ['nullable', 'numeric'],
            'rows.*.material_cost' => ['nullable', 'numeric'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $period = ReportPeriod::query()->firstOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            ['total_days' => $totalDays, 'total_hours' => $totalDays * 24],
        );

        // Master code lookups (global) + this unit's machines by name.
        $types = MaintenanceType::query()->pluck('id', 'code');
        $groups = WorkGroup::query()->pluck('id', 'code');
        $statuses = WoStatus::query()->pluck('id', 'code');
        $cycles = MaintenanceCycle::query()->pluck('id', 'code');
        $machines = Machine::query()->where('unit_id', $unit->id)->pluck('id', 'name');
        $reasons = collect(WoWaitingReason::cases())->map(fn (WoWaitingReason $r): string => $r->value);

        $submittedWonums = [];

        DB::transaction(function () use ($validated, $unit, $period, $user, $types, $groups, $statuses, $cycles, $machines, $reasons, &$submittedWonums): void {
            foreach ($validated['rows'] ?? [] as $row) {
                $wonum = trim((string) ($row['wonum'] ?? ''));
                if ($wonum === '') {
                    continue;
                }
                $submittedWonums[] = $wonum;

                $reason = in_array($row['waiting_reason'] ?? null, $reasons->all(), true)
                    ? $row['waiting_reason']
                    : null;

                WorkOrder::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'report_period_id' => $period->id, 'wonum' => $wonum],
                    [
                        'description' => $row['description'] ?? null,
                        'maintenance_type_id' => $types[$row['type_code'] ?? ''] ?? null,
                        'engine_id' => $machines[$row['engine_name'] ?? ''] ?? null,
                        'work_group_id' => $groups[$row['work_group_code'] ?? ''] ?? null,
                        'wo_status_id' => $statuses[$row['status_code'] ?? ''] ?? null,
                        'cycle_id' => $cycles[$row['cycle_code'] ?? ''] ?? null,
                        'report_date' => $row['report_date'] ?? null,
                        'sched_start' => $row['sched_start'] ?? null,
                        'sched_finish' => $row['sched_finish'] ?? null,
                        'waiting_reason' => $reason,
                        'service_cost' => $row['service_cost'] ?? null,
                        'material_cost' => $row['material_cost'] ?? null,
                        'source' => WorkOrderSource::Manual,
                        'input_by' => $user->id,
                    ],
                );
            }

            // Rows removed from the grid are removed from this period.
            WorkOrder::query()
                ->where('unit_id', $unit->id)
                ->where('report_period_id', $period->id)
                ->whereNotIn('wonum', $submittedWonums === [] ? [''] : $submittedWonums)
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Work Order {$unit->name} periode {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Work Order disimpan.']);

        return back();
    }
}
