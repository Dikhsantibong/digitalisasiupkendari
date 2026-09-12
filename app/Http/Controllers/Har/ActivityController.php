<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MaintenanceActivity;
use App\Models\MaintenanceType;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HARMES activity log input (modul HAR): the core field-work record. Each
 * activity carries its task lines and the materials used.
 */
class ActivityController extends Controller
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

        $activities = $period === null ? collect() : MaintenanceActivity::query()
            ->where('unit_id', $unit->id)
            ->where('report_period_id', $period->id)
            ->with(['engine:id,name', 'workOrder:id,wonum', 'tasks', 'materials'])
            ->withCount(['tasks', 'materials'])
            ->orderByDesc('activity_date')
            ->get();

        return Inertia::render('har/input/activities', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'activities' => $activities->map(fn (MaintenanceActivity $a): array => [
                'id' => $a->id,
                'activity_date' => $a->activity_date?->toDateString(),
                'engine_id' => $a->engine_id,
                'engine_name' => $a->engine?->name,
                'maintenance_type_id' => $a->maintenance_type_id,
                'wo_id' => $a->wo_id,
                'wonum' => $a->workOrder?->wonum,
                'work_result' => $a->work_result,
                'no_lh05' => $a->no_lh05,
                'no_sr' => $a->no_sr,
                'no_tug9' => $a->no_tug9,
                'keterangan' => $a->keterangan,
                'tasks' => $a->tasks->map(fn ($t): array => ['task_description' => $t->task_description])->all(),
                'materials' => $a->materials->map(fn ($m): array => [
                    'material_name' => $m->material_name,
                    'part_number' => $m->part_number,
                    'quantity' => $m->quantity,
                    'unit_of_measure' => $m->unit_of_measure,
                ])->all(),
                'tasks_count' => $a->tasks_count,
                'materials_count' => $a->materials_count,
            ])->all(),
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'machines' => Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'maintenance_types' => MaintenanceType::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
                'work_orders' => $period === null ? [] : WorkOrder::query()->where('unit_id', $unit->id)->where('report_period_id', $period->id)->orderBy('wonum')->get(['id', 'wonum']),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, MaintenanceActivity $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessUnit($activity->unit_id), 403);

        return $this->persist($request, $activity);
    }

    public function destroy(Request $request, MaintenanceActivity $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($activity->unit_id), 403);

        $activity->delete();

        $this->activityLogger->log(ActivityEvent::Deleted, 'Menghapus kegiatan HARMES', unit: $activity->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kegiatan dihapus.']);

        return back();
    }

    private function persist(Request $request, ?MaintenanceActivity $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'activity_date' => ['required', 'date'],
            'engine_id' => ['nullable', 'integer', Rule::exists('machines', 'id')->where('unit_id', $unit->id)],
            'maintenance_type_id' => ['nullable', 'integer', 'exists:maintenance_types,id'],
            'wo_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('unit_id', $unit->id)],
            'work_result' => ['nullable', 'string', 'max:150'],
            'no_lh05' => ['nullable', 'string', 'max:100'],
            'no_sr' => ['nullable', 'string', 'max:100'],
            'no_tug9' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'tasks' => ['array'],
            'tasks.*.task_description' => ['required', 'string', 'max:500'],
            'materials' => ['array'],
            'materials.*.material_name' => ['required', 'string', 'max:150'],
            'materials.*.part_number' => ['nullable', 'string', 'max:100'],
            'materials.*.quantity' => ['nullable', 'numeric'],
            'materials.*.unit_of_measure' => ['nullable', 'string', 'max:30'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $period = ReportPeriod::query()->firstOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            ['total_days' => $totalDays, 'total_hours' => $totalDays * 24],
        );

        DB::transaction(function () use ($validated, $unit, $period, $user, &$activity): void {
            $attributes = [
                'unit_id' => $unit->id,
                'report_period_id' => $period->id,
                'activity_date' => $validated['activity_date'],
                'engine_id' => $validated['engine_id'] ?? null,
                'maintenance_type_id' => $validated['maintenance_type_id'] ?? null,
                'wo_id' => $validated['wo_id'] ?? null,
                'work_result' => $validated['work_result'] ?? null,
                'no_lh05' => $validated['no_lh05'] ?? null,
                'no_sr' => $validated['no_sr'] ?? null,
                'no_tug9' => $validated['no_tug9'] ?? null,
                'keterangan' => $validated['keterangan'] ?? null,
                'input_by' => $user->id,
            ];

            if ($activity === null) {
                $activity = MaintenanceActivity::query()->create($attributes);
            } else {
                $activity->update($attributes);
                $activity->tasks()->delete();
                $activity->materials()->delete();
            }

            foreach (array_values($validated['tasks'] ?? []) as $order => $task) {
                $activity->tasks()->create([
                    'task_description' => $task['task_description'],
                    'sort_order' => $order,
                ]);
            }

            foreach ($validated['materials'] ?? [] as $material) {
                $activity->materials()->create([
                    'material_name' => $material['material_name'],
                    'part_number' => $material['part_number'] ?? null,
                    'quantity' => $material['quantity'] ?? null,
                    'unit_of_measure' => $material['unit_of_measure'] ?? null,
                ]);
            }
        });

        $this->activityLogger->log(
            $activity->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            'Menyimpan kegiatan HARMES '.$unit->name,
            $activity,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kegiatan disimpan.']);

        return back();
    }
}
