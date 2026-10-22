<?php

namespace App\Services\Operator;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Enums\ScheduleGroupType;
use App\Models\ActivityLog;
use App\Models\AttendanceCode;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeePresence;
use App\Models\HarUnsafeCondition;
use App\Models\KondisiAbnormal;
use App\Models\OperasiFlmMonitoring;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\OperasiProgram5s5rItem;
use App\Models\OperatorLogsheet;
use App\Models\OperatorMutasi;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resume Statistik — realisasi shift harian per operator for a unit & month:
 * the Jadwal Shift against the presensi ({@see AttendanceCalculator}: hadir,
 * terlambat, tidak hadir, % hadir, izin / sakit / cuti), the tukar shift
 * done, and the work each one saved under their own account (logsheet, lembar
 * mutasi, patrol check, kondisi abnormal, unsafe, FLM, 5S5R and every other
 * save in the activity log). Per day for one employee: the planned shift, the
 * absen masuk / pulang and what they saved that day.
 *
 * Nothing is stored: it is read from the source tables every time.
 */
class OperatorResume
{
    /** Pekerjaan columns => label, in display order. */
    public const PEKERJAAN = [
        'logsheet' => 'Logsheet',
        'mutasi' => 'Lembar Mutasi',
        'patrol_check' => 'Patrol Check Mesin',
        'kondisi_abnormal' => 'Kondisi Abnormal',
        'unsafe' => 'Unsafe Action/Condition',
        'flm' => 'Monitoring FLM',
        'program_5s5r' => 'Program 5S5R',
        'aktivitas' => 'Total Aktivitas',
    ];

    /** The absence codes counted apart. */
    public const ABSENCE_CODES = ['I' => 'Izin', 'SKT' => 'Sakit', 'C' => 'Cuti', 'A' => 'Alpha'];

    private const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function __construct(
        private readonly AttendanceRoster $roster,
        private readonly AttendanceCalculator $calculator,
    ) {}

    /**
     * One row per employee on the unit's absensi sheet (Kerja Shift, then Non
     * Shift), or only the given one.
     *
     * @return list<array<string, mixed>>
     */
    public function employees(Unit $unit, int $month, int $year, ?Employee $only = null): array
    {
        [$from, $to] = $this->period($month, $year);
        $codes = AttendanceCode::query()->get()->keyBy('id');
        $rows = [];

        foreach ([ScheduleGroupType::Shift, ScheduleGroupType::NonShift] as $group) {
            $employees = $this->roster->employees($unit, $group);
            if ($only !== null) {
                $employees = $employees->where('id', $only->id)->values();
            }
            if ($employees->isEmpty()) {
                continue;
            }

            $ids = $employees->pluck('id')->all();
            // The roster reads a few columns only; the account links what each one saved.
            $accounts = Employee::query()->whereIn('id', $ids)->pluck('user_id', 'id');
            $employees->each(fn (Employee $employee) => $employee->setAttribute('user_id', $accounts[$employee->id] ?? null));
            $summary = $this->calculator->summarise(
                $this->entries($unit, $month, $year, $ids),
                $codes,
                EmployeePresence::query()->whereIn('employee_id', $ids)
                    ->whereDate('work_date', '>=', $from->toDateString())->whereDate('work_date', '<=', $to->toDateString())->get(),
            )['per_employee'];
            $tukar = $this->tukarShift($unit, $ids, $from, $to);

            foreach ($employees as $employee) {
                $attendance = $summary[$employee->id] ?? null;
                $counts = $attendance['counts'] ?? [];

                $rows[] = [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $employee->position,
                    'regu' => $employee->regu,
                    'is_shift_leader' => (bool) $employee->is_shift_leader,
                    'group' => $group->value,
                    'has_account' => $employee->user_id !== null,
                    'kehadiran' => [
                        'jadwal_kerja' => (int) ($attendance['jadwal_kerja'] ?? 0),
                        'shift' => ['P' => $counts['P'] ?? 0, 'S' => $counts['S'] ?? 0, 'M' => $counts['M'] ?? 0, 'OFF' => $counts['OFF'] ?? 0],
                        'hadir' => (int) ($attendance['hadir'] ?? 0),
                        'terlambat' => (int) ($attendance['terlambat'] ?? 0),
                        'tidak_hadir' => (int) ($attendance['tidak_hadir'] ?? 0),
                        'di_luar_jadwal' => (int) ($attendance['di_luar_jadwal'] ?? 0),
                        'percent' => $attendance['percent'] ?? null,
                        'absen' => collect(self::ABSENCE_CODES)->keys()->mapWithKeys(fn (string $code): array => [$code => $counts[$code] ?? 0])->all(),
                    ],
                    'tukar_shift' => $tukar[$employee->id] ?? ['menggantikan' => 0, 'digantikan' => 0],
                    'pekerjaan' => $this->pekerjaan($unit, $employee, $month, $year, $from, $to),
                ];
            }
        }

        return $rows;
    }

    /**
     * Every day of the month for one employee: the planned code, the absen
     * masuk / pulang, the attendance status, the pengajuan note and what was
     * saved under their account that day.
     *
     * @return list<array<string, mixed>>
     */
    public function days(Unit $unit, Employee $employee, int $month, int $year): array
    {
        [$from, $to] = $this->period($month, $year);
        $codes = AttendanceCode::query()->get()->keyBy('id');
        $today = Carbon::now(PresenceRecorder::TIMEZONE)->toDateString();

        $entries = $this->entries($unit, $month, $year, [$employee->id])->keyBy(fn (WorkScheduleEntry $e): string => $e->work_date->toDateString());
        $presences = EmployeePresence::query()->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $from->toDateString())->whereDate('work_date', '<=', $to->toDateString())->get()
            ->keyBy(fn (EmployeePresence $p): string => $p->work_date->toDateString());

        $activities = $employee->user_id === null ? collect() : ActivityLog::query()
            ->where('user_id', $employee->user_id)
            ->where('created_at', '>=', $from->copy()->shiftTimezone(PresenceRecorder::TIMEZONE)->utc())
            ->where('created_at', '<=', $to->copy()->endOfDay()->shiftTimezone(PresenceRecorder::TIMEZONE)->utc())
            ->orderBy('created_at')
            ->get(['id', 'description', 'created_at'])
            ->groupBy(fn (ActivityLog $log): string => $log->created_at->timezone(PresenceRecorder::TIMEZONE)->toDateString());

        $logsheets = $this->logsheetQuery($unit, $employee, $from, $to)->get(['log_date'])
            ->countBy(fn (OperatorLogsheet $sheet): string => $sheet->log_date->toDateString());

        $days = [];
        for ($date = $from->copy(); $date->lte($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $entry = $entries->get($key);
            $code = $entry?->attendance_code_id !== null ? $codes->get($entry->attendance_code_id) : null;
            $presence = $presences->get($key);
            $working = $code !== null && $code->hitung_hadir;

            $days[] = [
                'day' => $date->day,
                'date' => $key,
                'name' => self::DAY_NAMES[$date->dayOfWeek],
                'code' => $code?->code,
                'code_label' => $code?->label,
                'working' => $working,
                'note' => $entry?->attendance_request_id !== null ? $entry->note : null,
                'check_in' => $presence?->check_in_at->timezone(PresenceRecorder::TIMEZONE)->format('H:i'),
                'check_out' => $presence?->check_out_at?->timezone(PresenceRecorder::TIMEZONE)->format('H:i'),
                'late_minutes' => $presence?->late_minutes,
                'status' => $this->calculator->status($working, $presence, $key, $today),
                'logsheet' => (int) ($logsheets[$key] ?? 0),
                'aktivitas' => ($activities->get($key) ?? collect())->map(fn (ActivityLog $log): array => [
                    'time' => $log->created_at->timezone(PresenceRecorder::TIMEZONE)->format('H:i'),
                    'description' => $log->description,
                ])->values()->all(),
            ];
        }

        return $days;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return Collection<int, WorkScheduleEntry>
     */
    private function entries(Unit $unit, int $month, int $year, array $employeeIds): Collection
    {
        $schedules = WorkSchedule::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->pluck('id');

        return WorkScheduleEntry::query()->whereIn('work_schedule_id', $schedules)->whereIn('employee_id', $employeeIds)->get();
    }

    /**
     * Approved tukar shift of the month per employee: shifts worked for a
     * colleague (menggantikan) and shifts handed over (digantikan).
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array{menggantikan: int, digantikan: int}>
     */
    private function tukarShift(Unit $unit, array $employeeIds, Carbon $from, Carbon $to): array
    {
        $result = [];
        $requests = AttendanceRequest::query()->where('unit_id', $unit->id)
            ->where('type', AttendanceRequestType::TukarShift->value)
            ->where('status', AttendanceRequestStatus::Disetujui->value)
            ->get(['id', 'employee_id', 'substitute_employee_id', 'applied']);

        foreach ($requests as $request) {
            foreach ((array) $request->applied as $cell) {
                if (! in_array($cell['employee_id'], $employeeIds, true) || $cell['date'] < $from->toDateString() || $cell['date'] > $to->toDateString()) {
                    continue;
                }

                $result[$cell['employee_id']] ??= ['menggantikan' => 0, 'digantikan' => 0];
                $result[$cell['employee_id']][$cell['to'] === 'OFF' ? 'digantikan' : 'menggantikan']++;
            }
        }

        return $result;
    }

    /**
     * What the employee saved under their account in the month.
     *
     * @return array<string, int>
     */
    private function pekerjaan(Unit $unit, Employee $employee, int $month, int $year, Carbon $from, Carbon $to): array
    {
        $userId = $employee->user_id;
        $monthly = fn (string $model): int => $userId === null ? 0
            : $model::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->where('input_by', $userId)->count();

        return [
            'logsheet' => $this->logsheetQuery($unit, $employee, $from, $to)->count(),
            'mutasi' => $userId === null ? 0 : OperatorMutasi::query()->where('unit_id', $unit->id)->where('input_by', $userId)
                ->whereDate('tanggal', '>=', $from->toDateString())->whereDate('tanggal', '<=', $to->toDateString())->count(),
            'patrol_check' => $monthly(OperasiPatrolCheckMesin::class),
            'kondisi_abnormal' => $monthly(KondisiAbnormal::class),
            'unsafe' => $monthly(HarUnsafeCondition::class),
            'flm' => $monthly(OperasiFlmMonitoring::class),
            'program_5s5r' => $monthly(OperasiProgram5s5rItem::class),
            'aktivitas' => $userId === null ? 0 : ActivityLog::query()->where('user_id', $userId)
                ->where('created_at', '>=', $from->copy()->shiftTimezone(PresenceRecorder::TIMEZONE)->utc())
                ->where('created_at', '<=', $to->copy()->endOfDay()->shiftTimezone(PresenceRecorder::TIMEZONE)->utc())
                ->count(),
        ];
    }

    /**
     * The logsheets of the month the employee filled: named as the operator or saved under their account.
     *
     * @return Builder<OperatorLogsheet>
     */
    private function logsheetQuery(Unit $unit, Employee $employee, Carbon $from, Carbon $to): Builder
    {
        return OperatorLogsheet::query()->where('unit_id', $unit->id)
            ->whereDate('log_date', '>=', $from->toDateString())->whereDate('log_date', '<=', $to->toDateString())
            ->where(fn (Builder $query) => $query->where('operator_employee_id', $employee->id)
                ->when($employee->user_id !== null, fn (Builder $q) => $q->orWhere('input_by', $employee->user_id)));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(int $month, int $year): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();

        return [$from, $from->copy()->endOfMonth()->startOfDay()];
    }
}
