<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\AttendanceCodeType;
use App\Enums\PermissionName;
use App\Enums\ScheduleGroupType;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Services\ActivityLogger;
use App\Services\Operator\AttendanceCalculator;
use App\Services\Operator\AttendanceRoster;
use App\Support\Indonesian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absensi & Jadwal Kerja — part of the standalone OPERATOR module (not
 * OPERASI). One monthly sheet per unit showing both rosters from
 * {@see AttendanceRoster}: Kerja Shift (operators by regu) on top, Non Shift
 * (Project Leader & Koordinator) below. Rows are employees, columns the days of
 * the month, each cell an attendance code. Cells are stored per roster in the
 * matching {@see WorkSchedule} (group_type shift / non_shift) so the absensi
 * report keeps reading one group at a time. The recap (counts & % hadir) is
 * computed live on the page from the codes' `hitung_hadir` flag, matching
 * {@see AttendanceCalculator}.
 *
 * Guarded by operator.absensi.view (read) / .write (edit) plus a unit-scope
 * check. The project leader (a senior operator) and TL Operasi hold write.
 */
class AbsensiController extends Controller
{
    private const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly AttendanceRoster $roster,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));

        $codes = AttendanceCode::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $codeById = $codes->keyBy('id');

        $entries = WorkSchedule::query()->with('entries')
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()->flatMap->entries;

        $cells = [];
        foreach ($entries as $entry) {
            $code = $codeById->get($entry->attendance_code_id)?->code;
            if ($code !== null) {
                $cells[$entry->employee_id][(int) $entry->work_date->day] = $code;
            }
        }

        $holidays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date', 'description']);

        $days = collect(range(1, Carbon::create($year, $month, 1)->daysInMonth))
            ->map(function (int $day) use ($year, $month, $holidays): array {
                $date = Carbon::create($year, $month, $day);
                $holiday = $holidays->first(fn (Holiday $h): bool => $h->date->day === $day);

                return [
                    'day' => $day,
                    'name' => self::DAY_NAMES[$date->dayOfWeek],
                    'is_weekend' => $date->isWeekend(),
                    'is_holiday' => $holiday !== null,
                    'holiday' => $holiday?->description,
                ];
            })->all();

        $section = fn (ScheduleGroupType $group): array => [
            'key' => $group->value,
            'label' => $group === ScheduleGroupType::Shift ? 'Kerja Shift' : 'Non Shift',
            'employees' => $this->roster->employees($unit, $group)->map(fn (Employee $e): array => [
                'id' => $e->id,
                'name' => $e->name,
                'nip' => $e->nip,
                'position' => $e->position,
                'regu' => $e->regu,
                'is_shift_leader' => $e->is_shift_leader,
                'cells' => $cells[$e->id] ?? (object) [],
            ])->all(),
        ];

        return Inertia::render('operator/absensi', [
            'filters' => ['unit_id' => $unit->id, 'year' => $year, 'month' => $month],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 1, $now->year + 1),
            ],
            'days' => $days,
            'sections' => [$section(ScheduleGroupType::Shift), $section(ScheduleGroupType::NonShift)],
            'codes' => $codes->map(fn (AttendanceCode $c): array => [
                'code' => $c->code,
                'label' => $c->label,
                'type' => $c->type->value,
                'hitung_hadir' => $c->hitung_hadir,
                'jam_mulai' => $c->jam_mulai,
                'jam_selesai' => $c->jam_selesai,
            ])->all(),
            'patterns' => $this->roster->shiftSequences($unit)
                ->map(fn (array $codes, string $regu): array => ['regu' => $regu, 'sequence' => implode(', ', $codes)])
                ->values()->all(),
            'can_write' => $user->hasPermissionTo(PermissionName::OperatorAbsensiWrite),
        ]);
    }

    /**
     * Bulk upsert the month's cells. Each posted cell is an {employee_id, day,
     * code} triple routed to the employee's roster; a blank/unknown code clears
     * the cell and employees outside both rosters are ignored.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiWrite), 403);

        [$unit, $year, $month] = $this->resolveTarget($request);

        $validated = $request->validate([
            'cells' => ['array'],
            'cells.*.employee_id' => ['required', 'integer'],
            'cells.*.day' => ['required', 'integer', 'min:1', 'max:31'],
            'cells.*.code' => ['nullable', 'string'],
        ]);

        $codes = AttendanceCode::query()->where('is_active', true)->pluck('id', 'code');
        $groups = $this->rosterGroups($unit);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        DB::transaction(function () use ($unit, $year, $month, $validated, $codes, $groups, $daysInMonth, $user): void {
            $rows = [];

            foreach ($validated['cells'] ?? [] as $cell) {
                $member = $groups->get($cell['employee_id']);
                if ($member === null || $cell['day'] > $daysInMonth) {
                    continue;
                }

                $code = (string) ($cell['code'] ?? '');
                $rows[$member['group']->value][] = [
                    'employee_id' => $cell['employee_id'],
                    'day' => $cell['day'],
                    'attendance_code_id' => $code === '' ? null : ($codes[$code] ?? null),
                    'regu' => $member['regu'],
                ];
            }

            foreach ($rows as $group => $groupRows) {
                $this->putEntries($this->schedule($unit, $year, $month, ScheduleGroupType::from($group), $user->id), $year, $month, $groupRows);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan absensi {$unit->name} {$month}/{$year}",
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Absensi disimpan.']);

        return back();
    }

    /**
     * Fill the whole month from the patterns: operators follow their regu
     * rotation (continuing from last month where possible), Non Shift staff get
     * office hours. Absence codes already entered (Cuti, Sakit, Izin, Alpha) are
     * kept; everything stays editable afterwards.
     */
    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorAbsensiWrite), 403);

        [$unit, $year, $month] = $this->resolveTarget($request);

        $codes = AttendanceCode::query()->where('is_active', true)->get(['id', 'code', 'type']);
        $codeIds = $codes->pluck('id', 'code');
        $absenceIds = $codes->where('type', AttendanceCodeType::Absence)->pluck('id')->all();

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $sequences = $this->roster->shiftSequences($unit);
        $tails = $this->previousMonthTails($unit, $year, $month, $codes->pluck('code', 'id'));
        $holidayDays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date'])
            ->map(fn (Holiday $h): int => $h->date->day)->all();
        $officeHours = $this->roster->planNonShift($year, $month, $holidayDays);

        DB::transaction(function () use ($unit, $year, $month, $user, $codeIds, $absenceIds, $daysInMonth, $sequences, $tails, $officeHours): void {
            foreach (ScheduleGroupType::cases() as $group) {
                $schedule = $this->schedule($unit, $year, $month, $group, $user->id);
                $schedule->generated_at = now();
                $schedule->save();

                $kept = $schedule->entries()->whereIn('attendance_code_id', $absenceIds)->get()
                    ->map(fn ($entry): string => $entry->employee_id.':'.$entry->work_date->day)->flip();
                $rows = [];

                foreach ($this->roster->employees($unit, $group) as $employee) {
                    $plan = $group === ScheduleGroupType::Shift
                        ? ($sequences->has($employee->regu) ? $this->roster->planShift($sequences->get($employee->regu), $tails[$employee->id] ?? [], $daysInMonth) : [])
                        : $officeHours;

                    foreach ($plan as $index => $code) {
                        $day = $index + 1;
                        if ($kept->has($employee->id.':'.$day)) {
                            continue;
                        }

                        $rows[] = ['employee_id' => $employee->id, 'day' => $day, 'attendance_code_id' => $codeIds[$code] ?? null, 'regu' => $employee->regu];
                    }
                }

                $this->putEntries($schedule, $year, $month, $rows);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Isi otomatis absensi {$unit->name} {$month}/{$year}",
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal terisi otomatis. Cuti/Sakit/Izin/Alpha tetap dipertahankan.']);

        return back();
    }

    /**
     * @return array{0: Unit, 1: int, 2: int}
     */
    private function resolveTarget(Request $request): array
    {
        $request->validate([
            'unit_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        return [$unit, (int) $request->integer('year'), (int) $request->integer('month')];
    }

    private function schedule(Unit $unit, int $year, int $month, ScheduleGroupType $group, int $userId): WorkSchedule
    {
        $schedule = WorkSchedule::query()->firstOrNew([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'group_type' => $group->value,
        ]);
        $schedule->input_by = $userId;
        $schedule->save();

        return $schedule;
    }

    /**
     * Update or create the schedule's cells. Existing entries are matched in PHP
     * by employee + day: `work_date` is a date column, and an equality match on
     * it misses under SQLite (stored with a time), which would duplicate rows.
     *
     * @param  list<array{employee_id: int, day: int, attendance_code_id: int|null, regu: string|null}>  $rows
     */
    private function putEntries(WorkSchedule $schedule, int $year, int $month, array $rows): void
    {
        $existing = $schedule->entries()->get()->keyBy(fn ($entry): string => $entry->employee_id.':'.$entry->work_date->day);

        foreach ($rows as $row) {
            $attributes = ['attendance_code_id' => $row['attendance_code_id'], 'regu' => $row['regu']];
            $entry = $existing->get($row['employee_id'].':'.$row['day']);

            if ($entry !== null) {
                $entry->update($attributes);

                continue;
            }

            $schedule->entries()->create([
                'employee_id' => $row['employee_id'],
                'work_date' => Carbon::create($year, $month, $row['day'])->toDateString(),
                ...$attributes,
            ]);
        }
    }

    /**
     * Roster membership keyed by employee id.
     *
     * @return Collection<int, array{group: ScheduleGroupType, regu: string|null}>
     */
    private function rosterGroups(Unit $unit): Collection
    {
        $groups = collect();

        foreach (ScheduleGroupType::cases() as $group) {
            foreach ($this->roster->employees($unit, $group) as $employee) {
                $groups->put($employee->id, ['group' => $group, 'regu' => $employee->regu]);
            }
        }

        return $groups;
    }

    /**
     * The last two codes each shift employee worked in the previous month.
     *
     * @param  Collection<int, string>  $codeById
     * @return array<int, list<string>>
     */
    private function previousMonthTails(Unit $unit, int $year, int $month, Collection $codeById): array
    {
        $previous = Carbon::create($year, $month, 1)->subMonth();

        $schedule = WorkSchedule::query()
            ->where('unit_id', $unit->id)->where('year', $previous->year)->where('month', $previous->month)
            ->where('group_type', ScheduleGroupType::Shift->value)->first();

        if ($schedule === null) {
            return [];
        }

        $tails = [];
        $fromDay = $previous->daysInMonth - 1;

        foreach ($schedule->entries()->whereNotNull('attendance_code_id')->orderBy('work_date')->get() as $entry) {
            if ($entry->work_date->day >= $fromDay && $codeById->has($entry->attendance_code_id)) {
                $tails[$entry->employee_id][] = $codeById->get($entry->attendance_code_id);
            }
        }

        return array_filter($tails, fn (array $tail): bool => count($tail) === 2);
    }
}
