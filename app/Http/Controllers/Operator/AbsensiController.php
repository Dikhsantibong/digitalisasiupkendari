<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ScheduleGroupType;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ShiftPattern;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Services\ActivityLogger;
use App\Services\Operator\AttendanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absensi & Jadwal Kerja Shift — part of the standalone OPERATOR module (not
 * OPERASI). One monthly sheet per unit per employee group (shift / non-shift):
 * rows are employees from the existing Pegawai master, columns are the days of
 * the month, each cell an attendance code. Recap & attendance % are derived by
 * {@see AttendanceCalculator}. Cells are always hand-editable; "Generate pola"
 * only pre-fills from the per-regu {@see ShiftPattern} as a starting point.
 *
 * Guarded by operator.absensi.view (read) / .write (edit) plus a unit-scope
 * check. The project leader (a senior operator) and TL Operasi hold write.
 */
class AbsensiController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly AttendanceCalculator $calculator,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $groupType = ScheduleGroupType::tryFrom((string) $request->query('group_type')) ?? ScheduleGroupType::Shift;
        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));

        $codes = AttendanceCode::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        $employees = Employee::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->when(
                $groupType === ScheduleGroupType::Shift,
                fn ($q) => $q->whereNotNull('regu'),
                fn ($q) => $q->whereNull('regu'),
            )
            ->orderByRaw('regu is null, regu')->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'regu']);

        $schedule = WorkSchedule::query()
            ->with('entries')
            ->where('unit_id', $unit->id)->where('year', $year)
            ->where('month', $month)->where('group_type', $groupType->value)
            ->first();

        $entries = $schedule?->entries ?? collect();

        // cells[employeeId][day] = code string
        $cells = [];
        foreach ($entries as $entry) {
            if ($entry->attendance_code_id === null) {
                continue;
            }
            $code = $codes->firstWhere('id', $entry->attendance_code_id)?->code;
            if ($code !== null) {
                $cells[$entry->employee_id][(int) $entry->work_date->day] = $code;
            }
        }

        $recap = $this->calculator->summarise($entries, $codes->keyBy('id'));

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $holidayDates = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)
            ->get(['date', 'description']);

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidayDates): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidayDates->first(fn ($h): bool => $h->date->day === $day);

            return [
                'day' => $day,
                'dow' => $this->dayInitial($date),
                'is_weekend' => $date->isSunday(),
                'is_holiday' => $holiday !== null,
                'holiday' => $holiday?->description,
            ];
        })->all();

        return Inertia::render('operator/absensi', [
            'filters' => [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'group_type' => $groupType->value,
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 1, $now->year + 1),
                'group_types' => array_map(
                    fn (ScheduleGroupType $t): array => ['value' => $t->value, 'label' => $t->label()],
                    ScheduleGroupType::cases(),
                ),
            ],
            'days' => $days,
            'employees' => $employees->map(fn (Employee $e): array => [
                'id' => $e->id,
                'name' => $e->name,
                'nip' => $e->nip,
                'position' => $e->position,
                'regu' => $e->regu,
                'cells' => $cells[$e->id] ?? (object) [],
                'recap' => $recap['per_employee'][$e->id]['counts'] ?? (object) [],
                'present' => $recap['per_employee'][$e->id]['present'] ?? 0,
                'scheduled' => $recap['per_employee'][$e->id]['scheduled'] ?? 0,
                'percent' => $recap['per_employee'][$e->id]['percent'] ?? null,
            ])->all(),
            'codes' => $codes->map(fn (AttendanceCode $c): array => [
                'code' => $c->code,
                'label' => $c->label,
                'type' => $c->type->value,
                'hitung_hadir' => $c->hitung_hadir,
            ])->all(),
            'totals' => $recap['totals'],
            'patterns' => ShiftPattern::query()->where('unit_id', $unit->id)->where('is_active', true)
                ->get(['regu', 'sequence'])
                ->map(fn (ShiftPattern $p): array => ['regu' => $p->regu, 'sequence' => $p->sequence])->all(),
            'can_write' => $user->hasPermissionTo(PermissionName::OperatorAbsensiWrite),
        ]);
    }

    /**
     * Bulk upsert the month's cells for the unit/group. Each posted cell is an
     * {employee_id, day, code} triple; a null/unknown code clears that cell.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiWrite), 403);

        [$unit, $groupType, $year, $month] = $this->resolveTarget($request);

        $codes = AttendanceCode::query()->where('is_active', true)->pluck('id', 'code');

        $validated = $request->validate([
            'cells' => ['array'],
            'cells.*.employee_id' => ['required', 'integer'],
            'cells.*.day' => ['required', 'integer', 'min:1', 'max:31'],
            'cells.*.code' => ['nullable', 'string'],
        ]);

        $employeeIds = Employee::query()->where('unit_id', $unit->id)->pluck('regu', 'id');
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        DB::transaction(function () use ($unit, $groupType, $year, $month, $validated, $codes, $employeeIds, $daysInMonth, $user): WorkSchedule {
            $schedule = WorkSchedule::query()->firstOrNew([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'group_type' => $groupType->value,
            ]);
            $schedule->input_by = $user->id;
            $schedule->save();

            foreach ($validated['cells'] ?? [] as $cell) {
                if (! $employeeIds->has($cell['employee_id']) || $cell['day'] > $daysInMonth) {
                    continue;
                }

                $codeId = $cell['code'] !== null && $cell['code'] !== ''
                    ? ($codes[$cell['code']] ?? null)
                    : null;

                $workDate = Carbon::create($year, $month, $cell['day'])->toDateString();

                $schedule->entries()->updateOrCreate(
                    ['employee_id' => $cell['employee_id'], 'work_date' => $workDate],
                    ['attendance_code_id' => $codeId, 'regu' => $employeeIds->get($cell['employee_id'])],
                );
            }

            return $schedule;
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan jadwal {$groupType->label()} {$unit->name} {$month}/{$year}",
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal disimpan.']);

        return back();
    }

    /**
     * Pre-fill the month from the per-regu shift patterns, starting on the given
     * day. Existing cells are overwritten for shift employees; everything stays
     * editable afterwards. Only meaningful for the shift group.
     */
    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiWrite), 403);

        [$unit, $groupType, $year, $month] = $this->resolveTarget($request);
        $validated = $request->validate(['start_day' => ['nullable', 'integer', 'min:1', 'max:31']]);
        $startDay = (int) ($validated['start_day'] ?? 1);

        $patterns = ShiftPattern::query()->where('unit_id', $unit->id)->where('is_active', true)->get()
            ->mapWithKeys(fn (ShiftPattern $p): array => [$p->regu => $p->codes()]);
        abort_if($patterns->isEmpty(), 422, 'Belum ada pola shift untuk unit ini.');

        $codes = AttendanceCode::query()->where('is_active', true)->pluck('id', 'code');
        $employees = Employee::query()->where('unit_id', $unit->id)->where('is_active', true)
            ->whereNotNull('regu')->get(['id', 'regu']);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        DB::transaction(function () use ($unit, $groupType, $year, $month, $startDay, $patterns, $codes, $employees, $daysInMonth, $user): void {
            $schedule = WorkSchedule::query()->firstOrNew([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'group_type' => $groupType->value,
            ]);
            $schedule->input_by = $user->id;
            $schedule->generated_at = now();
            $schedule->save();

            foreach ($employees as $employee) {
                $sequence = $patterns->get($employee->regu);
                if (empty($sequence)) {
                    continue;
                }

                for ($day = $startDay; $day <= $daysInMonth; $day++) {
                    $code = $sequence[($day - $startDay) % count($sequence)];
                    $workDate = Carbon::create($year, $month, $day)->toDateString();

                    $schedule->entries()->updateOrCreate(
                        ['employee_id' => $employee->id, 'work_date' => $workDate],
                        ['attendance_code_id' => $codes[$code] ?? null, 'regu' => $employee->regu],
                    );
                }
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Generate pola shift {$unit->name} {$month}/{$year}",
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pola shift dibuat. Semua sel tetap bisa diedit.']);

        return back();
    }

    /**
     * @return array{0: Unit, 1: ScheduleGroupType, 2: int, 3: int}
     */
    private function resolveTarget(Request $request): array
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        $request->validate([
            'unit_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'group_type' => ['required', Rule::in(array_column(ScheduleGroupType::cases(), 'value'))],
        ]);

        return [
            $unit,
            ScheduleGroupType::from($request->string('group_type')->value()),
            (int) $request->integer('year'),
            (int) $request->integer('month'),
        ];
    }

    private function dayInitial(Carbon $date): string
    {
        return ['M', 'S', 'S', 'R', 'K', 'J', 'S'][$date->dayOfWeek];
    }
}
