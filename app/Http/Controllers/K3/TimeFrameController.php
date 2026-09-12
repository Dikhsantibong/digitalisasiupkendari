<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\SchedulePlanType;
use App\Http\Controllers\Controller;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
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
 * Time Frame (modul K3): the plan-vs-realisation matrix of K3 activities over
 * the days of a month. One matrix per plan type (rencana / realisasi); each cell
 * is a day mark, kept flexible as JSON on the activity's plan row.
 */
class TimeFrameController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        $planType = SchedulePlanType::tryFrom((string) $request->query('plan_type')) ?? SchedulePlanType::Rencana;
        $days = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $field = $planType === SchedulePlanType::Rencana ? 'plan_days' : 'real_days';

        $types = K3ActivityType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $stored = K3ActivityPlan::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()->keyBy('k3_activity_type_id');

        $rows = $types->map(function (K3ActivityType $type) use ($stored, $days, $field): array {
            $plan = $stored->get($type->id);
            $data = $plan?->{$field} ?? [];
            $row = ['activity_type_id' => $type->id, 'activity_name' => $type->name, 'pic' => $plan?->pic ?? $type->default_pic];
            for ($d = 1; $d <= $days; $d++) {
                $row['day_'.$d] = $data[(string) $d] ?? null;
            }

            return $row;
        })->all();

        return Inertia::render('k3/input/time-frame', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'plan_type' => $planType->value],
            'days' => $days,
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'plan_types' => collect(SchedulePlanType::cases())->map(fn (SchedulePlanType $p): array => ['value' => $p->value, 'label' => $p->label()])->all(),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'plan_type' => ['required', new Enum(SchedulePlanType::class)],
            'rows' => ['array'],
            'rows.*.activity_type_id' => ['required', 'integer', Rule::exists('k3_activity_types', 'id')],
            'rows.*.pic' => ['nullable', 'string', 'max:255'],
            'rows.*.days' => ['array'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $planType = SchedulePlanType::from($validated['plan_type']);
        $field = $planType === SchedulePlanType::Rencana ? 'plan_days' : 'real_days';

        DB::transaction(function () use ($validated, $unit, $month, $year, $field, $user): void {
            foreach ($validated['rows'] ?? [] as $row) {
                $data = collect($row['days'] ?? [])
                    ->filter(fn ($value): bool => $value !== null && trim((string) $value) !== '')
                    ->map(fn ($value): string => trim((string) $value))
                    ->all();

                K3ActivityPlan::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'k3_activity_type_id' => (int) $row['activity_type_id']],
                    [$field => $data, 'pic' => $row['pic'] ?? null, 'input_by' => $user->id],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Time Frame K3 {$planType->value} {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Time Frame disimpan.']);

        return back();
    }
}
