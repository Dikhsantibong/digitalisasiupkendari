<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\MaintenanceScope;
use App\Enums\PermissionName;
use App\Enums\SchedulePlanType;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan-vs-realisation schedule matrix (modul HAR). One matrix per machine, for a
 * scope (HAR / pelumas / air) and a plan type (rencana / realisasi); each cell
 * is a day of the month. Kept flexible as JSON.
 */
class ScheduleController extends Controller
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
        $scope = MaintenanceScope::tryFrom((string) $request->query('scope')) ?? MaintenanceScope::Har;
        $planType = SchedulePlanType::tryFrom((string) $request->query('plan_type')) ?? SchedulePlanType::Rencana;
        $days = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $stored = MaintenanceSchedule::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->where('scope', $scope->value)->where('plan_type', $planType->value)
            ->get()->keyBy('engine_id');

        $rows = $machines->map(function (Machine $machine) use ($stored, $days): array {
            $data = $stored->get($machine->id)?->schedule_data ?? [];
            $row = ['engine_id' => $machine->id, 'engine_name' => $machine->name];
            for ($d = 1; $d <= $days; $d++) {
                $row['day_'.$d] = $data[(string) $d] ?? null;
            }

            return $row;
        })->all();

        return Inertia::render('har/input/schedules', [
            'filters' => [
                'unit_id' => $unit->id, 'month' => $month, 'year' => $year,
                'scope' => $scope->value, 'plan_type' => $planType->value,
            ],
            'days' => $days,
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'scopes' => collect(MaintenanceScope::cases())->map(fn (MaintenanceScope $s): array => ['value' => $s->value, 'label' => $s->label()])->all(),
                'plan_types' => collect(SchedulePlanType::cases())->map(fn (SchedulePlanType $p): array => ['value' => $p->value, 'label' => $p->label()])->all(),
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
            'scope' => ['required', new Enum(MaintenanceScope::class)],
            'plan_type' => ['required', new Enum(SchedulePlanType::class)],
            'rows' => ['array'],
            'rows.*.engine_id' => ['required', 'integer', Rule::exists('machines', 'id')->where('unit_id', $unit->id)],
            'rows.*.days' => ['array'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            foreach ($validated['rows'] ?? [] as $row) {
                $data = collect($row['days'] ?? [])
                    ->filter(fn ($value): bool => $value !== null && trim((string) $value) !== '')
                    ->map(fn ($value): string => trim((string) $value))
                    ->all();

                MaintenanceSchedule::query()->updateOrCreate(
                    [
                        'unit_id' => $unit->id, 'year' => $year, 'month' => $month,
                        'engine_id' => (int) $row['engine_id'],
                        'scope' => $validated['scope'], 'plan_type' => $validated['plan_type'],
                    ],
                    ['schedule_data' => $data, 'input_by' => $user->id],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan jadwal {$validated['scope']}/{$validated['plan_type']} {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal disimpan.']);

        return back();
    }
}
