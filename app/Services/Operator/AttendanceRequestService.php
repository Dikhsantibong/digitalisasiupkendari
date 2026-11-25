<?php

namespace App\Services\Operator;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\ScheduleGroupType;
use App\Enums\UnitTlStructure;
use App\Models\AttendanceCode;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use App\Services\AccessControl;
use App\Support\Indonesian;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The rules of a Pengajuan Absensi and what an approval does to the Jadwal
 * Shift — the single place both live, used by the Pengajuan page and its tests.
 *
 * - Izin / sakit / cuti: every day of the range that is not an OFF day gets
 *   the absence code (I, SKT, C), so it never counts against % hadir.
 * - Tukar shift (simplified, user 2026-11-24): the employee picks a colleague
 *   and their two Jadwal Shift codes on `start_date` are exchanged (a blank
 *   day counts as OFF) — e.g. A on Pagi with B on Sore. No schedule rule is
 *   checked: the colleague confirms and the TL decides. A legacy `swap_date`
 *   is exchanged the same way.
 *
 * Every changed cell carries attendance_request_id and a note, and is kept in
 * `applied` with its previous code, so "Isi Otomatis" leaves it alone and
 * cancelling the approval puts the previous codes back.
 */
class AttendanceRequestService
{
    /** Longest izin / sakit / cuti range, in days. */
    public const MAX_DAYS = 31;

    public function __construct(
        private readonly AttendanceRoster $roster,
        private readonly AccessControl $accessControl,
    ) {}

    /**
     * Whether this request was submitted by a HAR field technician (Harmes,
     * Harlist, ASME & Elec / Asmec / Aslist) and therefore routes to TL
     * Pemeliharaan instead of TL Operasi.
     */
    public function isHarRequest(AttendanceRequest $request): bool
    {
        $request->loadMissing(['employee.user', 'requester']);

        $employee = $request->employee;
        if ($employee !== null && $employee->regu !== null && $employee->regu !== '') {
            return false;
        }

        if ($employee !== null && (Employee::isHarFieldPosition($employee->position) || $employee->division === 'pemeliharaan')) {
            return true;
        }

        foreach (array_filter([$request->requester, $employee?->user]) as $account) {
            if ($account->isHarFieldAccount()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the user holds any Pengajuan Absensi approval capability in the unit.
     */
    public function canApproveAny(User $user, Unit|int $unit): bool
    {
        if ($user->isReadOnly() || ! $user->canAccessUnit($unit)) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionName::OperatorPengajuanApproveOperasi)
            || $user->hasPermissionTo(PermissionName::OperatorPengajuanApproveHar);
    }

    /**
     * Whether the user may approve, reject or undo this request: never their
     * own request; HAR requests route to TL Pemeliharaan (or TL Ophar, or
     * Project Leader in a containerized unit), while Operator / Operasi requests
     * route to TL Operasi (or TL Ophar, Project Leader, Koordinator Operasi).
     */
    public function canApprove(User $user, AttendanceRequest $request): bool
    {
        if ($user->isReadOnly() || ! $user->canAccessUnit($request->unit_id)) {
            return false;
        }

        $request->loadMissing(['employee.user', 'requester', 'unit']);

        if ($this->isOwnRequest($user, $request)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->isHarRequest($request)) {
            return $this->canApproveHarOnUnit($user, $request->unit);
        }

        return $user->hasPermissionTo(PermissionName::OperatorPengajuanApproveOperasi);
    }

    public function isOwnRequest(User $user, AttendanceRequest $request): bool
    {
        $request->loadMissing('employee');

        if ($request->employee?->user_id === $user->id) {
            return true;
        }

        $user->loadMissing('employee');

        return $user->employee !== null && $user->employee->is_active && $user->employee->id === $request->employee_id;
    }

    private function canApproveHarOnUnit(User $user, ?Unit $unit): bool
    {
        if ($user->hasPermissionTo(PermissionName::OperatorPengajuanApproveHar)) {
            return true;
        }

        if ($unit === null || $unit->tlStructure() !== UnitTlStructure::Containerized) {
            return false;
        }

        return $this->accessControl->roleNames($user)->contains(RoleName::ProjectLeaderOperasi->value)
            || $user->employee?->canonicalPosition() === EmployeePosition::ProjectLeader;
    }

    /**
     * The employees on the unit's absensi sheet (Kerja Shift, then Non Shift).
     *
     * @return Collection<int, Employee>
     */
    public function rosterOf(Unit $unit): Collection
    {
        return $this->roster->employees($unit, ScheduleGroupType::Shift)
            ->concat($this->roster->employees($unit, ScheduleGroupType::NonShift))
            ->values();
    }

    public function onRoster(Unit $unit, Employee $employee): bool
    {
        return $this->rosterOf($unit)->contains('id', $employee->id);
    }

    /**
     * The Jadwal Shift code of an employee on a date, or null when blank.
     */
    public function codeOn(Employee $employee, CarbonInterface|string $date): ?AttendanceCode
    {
        return $this->entryOn($employee, $date)?->attendanceCode;
    }

    /**
     * Schedule codes of employees over a date range, employee id → Y-m-d → code.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array<string, string>>
     */
    public function codesBetween(Unit $unit, array $employeeIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $codes = [];
        $entries = WorkScheduleEntry::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('schedule', fn ($query) => $query->where('unit_id', $unit->id))
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->whereNotNull('attendance_code_id')
            ->with('attendanceCode:id,code')
            ->get();

        foreach ($entries as $entry) {
            $codes[$entry->employee_id][$entry->work_date->toDateString()] = (string) $entry->attendanceCode?->code;
        }

        return $codes;
    }

    /** Active / approved request statuses that count against quotas and overlaps. */
    private const ACTIVE_STATUSES = [
        AttendanceRequestStatus::MenungguPengganti->value,
        AttendanceRequestStatus::MenungguPersetujuan->value,
        AttendanceRequestStatus::Disetujui->value,
    ];

    /**
     * Calendar months (`YYYY-MM`) where the employee has already used their
     * monthly Izin quota (pending or approved; rejected / cancelled do not count).
     *
     * @return list<string>
     */
    public function izinUsedMonths(Employee $employee): array
    {
        $months = [];
        $rows = AttendanceRequest::query()
            ->where('employee_id', $employee->id)
            ->where('type', AttendanceRequestType::Izin->value)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->get(['start_date', 'end_date']);

        foreach ($rows as $row) {
            $cursor = $row->start_date->copy()->startOfMonth();
            $last = $row->end_date->copy()->startOfMonth();

            while ($cursor->lte($last)) {
                $months[$cursor->format('Y-m')] = true;
                $cursor = $cursor->addMonthNoOverflow();
            }
        }

        $list = array_keys($months);
        sort($list);

        return $list;
    }

    /**
     * Check a new request: the range, the monthly Izin limit, the overlap with
     * the employee's other open or approved requests and, for a tukar shift, the
     * two schedules.
     *
     * @param  array{type: AttendanceRequestType, start_date: string, end_date: string, substitute: Employee|null, swap_date: string|null}  $data
     *
     * @throws ValidationException
     */
    public function validateNew(Unit $unit, Employee $employee, array $data): void
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        // Tukar shift: only a colleague on this unit's roster; the TL decides the rest (user, 2026-11-24).
        if ($data['type'] === AttendanceRequestType::TukarShift) {
            $substitute = $data['substitute'];
            if ($substitute === null || $substitute->id === $employee->id || ! $this->onRoster($unit, $substitute)) {
                throw ValidationException::withMessages(['substitute_employee_id' => 'Pilih rekan yang akan ditukar dari jadwal shift unit ini.']);
            }

            return;
        }

        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end_date' => 'Pengajuan paling lama '.self::MAX_DAYS.' hari.']);
        }

        if ($data['type'] === AttendanceRequestType::Izin) {
            $monthStart = $start->copy()->startOfMonth()->toDateString();
            $monthEnd = $end->copy()->endOfMonth()->toDateString();

            $hasMonthlyIzin = AttendanceRequest::query()
                ->where('employee_id', $employee->id)
                ->where('type', AttendanceRequestType::Izin->value)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereDate('start_date', '<=', $monthEnd)
                ->whereDate('end_date', '>=', $monthStart)
                ->exists();

            if ($hasMonthlyIzin) {
                $monthLabel = $start->isSameMonth($end)
                    ? Indonesian::monthName($start->month).' '.$start->year
                    : 'bulan tersebut';

                throw ValidationException::withMessages([
                    'type' => "Pengajuan izin hanya dapat dilakukan 1 kali per bulan (kuota {$monthLabel} sudah terpakai).",
                ]);
            }
        }

        $overlap = AttendanceRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'Sudah ada pengajuan Anda yang menunggu atau disetujui pada tanggal tersebut.']);
        }
    }

    /**
     * Write an approved request on the Jadwal Shift.
     *
     * @throws ValidationException when an absence code is missing from the master
     */
    public function apply(AttendanceRequest $request, int $userId): void
    {
        $request->loadMissing(['employee', 'substitute', 'unit']);
        $codes = AttendanceCode::query()->where('is_active', true)->get()->keyBy('code');

        DB::transaction(function () use ($request, $userId, $codes): void {
            $applied = [];
            $label = "{$request->type->label()} (pengajuan #{$request->id})";

            if ($request->type === AttendanceRequestType::TukarShift) {
                $off = $codes->get('OFF') ?? throw ValidationException::withMessages(['status' => 'Kode OFF belum ada di master kode absensi.']);
                $requester = $request->employee;
                $substitute = $request->substitute
                    ?? throw ValidationException::withMessages(['substitute_employee_id' => 'Rekan yang ditukar tidak ditemukan.']);

                $applied = [...$applied, ...$this->exchange($request, $requester, $substitute, $request->start_date, $off, $label, $userId)];
                if ($request->swap_date !== null && ! $request->swap_date->isSameDay($request->start_date)) {
                    $applied = [...$applied, ...$this->exchange($request, $requester, $substitute, $request->swap_date, $off, "balasan {$label}", $userId)];
                }
            } else {
                $absence = $codes->get((string) $request->type->attendanceCode())
                    ?? throw ValidationException::withMessages(['status' => "Kode {$request->type->attendanceCode()} belum ada di master kode absensi."]);

                for ($date = $request->start_date; $date->lte($request->end_date); $date = $date->addDay()) {
                    $current = $this->codeOn($request->employee, $date);
                    // A scheduled day off stays off: izin / sakit / cuti only cover working days.
                    if ($current !== null && $current->code === 'OFF') {
                        continue;
                    }

                    $applied[] = $this->put($request, $request->employee, $date, $absence, $label.': '.mb_strimwidth($request->reason, 0, 120, '…'), $userId);
                }
            }

            $request->applied = $applied;
            $request->save();
        });
    }

    /**
     * Put back the codes an approval replaced, on the cells still holding it.
     */
    public function revert(AttendanceRequest $request): void
    {
        $codes = AttendanceCode::query()->get()->keyBy('code');

        DB::transaction(function () use ($request, $codes): void {
            foreach ((array) $request->applied as $cell) {
                $entry = WorkScheduleEntry::query()
                    ->where('employee_id', $cell['employee_id'])
                    ->where('attendance_request_id', $request->id)
                    ->whereDate('work_date', $cell['date'])
                    ->first();

                $entry?->update([
                    'attendance_code_id' => $cell['from'] !== null ? $codes->get($cell['from'])?->id : null,
                    'note' => null,
                    'attendance_request_id' => null,
                ]);
            }
        });
    }

    /**
     * Exchange two employees' codes on a date (a blank day counts as OFF):
     * the employee takes the colleague's shift and the colleague the employee's.
     *
     * @return list<array{employee_id: int, date: string, from: string|null, to: string|null}>
     */
    private function exchange(AttendanceRequest $request, Employee $employee, Employee $colleague, CarbonInterface $date, AttendanceCode $off, string $label, int $userId): array
    {
        $mine = $this->codeOn($employee, $date) ?? $off;
        $theirs = $this->codeOn($colleague, $date) ?? $off;

        return [
            $this->put($request, $employee, $date, $theirs, "Tukar shift dengan {$colleague->name} ({$mine->code} → {$theirs->code}) — {$label}", $userId),
            $this->put($request, $colleague, $date, $mine, "Tukar shift dengan {$employee->name} ({$theirs->code} → {$mine->code}) — {$label}", $userId),
        ];
    }

    /**
     * Set one cell (creating the month's schedule or the cell when missing).
     *
     * @return array{employee_id: int, date: string, from: string|null, to: string|null}
     */
    private function put(AttendanceRequest $request, Employee $employee, CarbonInterface $date, AttendanceCode $code, string $note, int $userId): array
    {
        $entry = $this->entryOn($employee, $date, $request->unit_id);
        $from = $entry?->attendanceCode?->code;

        $values = [
            'attendance_code_id' => $code->id,
            'regu' => $employee->regu,
            'note' => mb_strimwidth($note, 0, 255, '…'),
            'attendance_request_id' => $request->id,
        ];

        if ($entry !== null) {
            $entry->update($values);
        } else {
            $schedule = WorkSchedule::query()->firstOrCreate([
                'unit_id' => $request->unit_id,
                'year' => $date->year,
                'month' => $date->month,
                'group_type' => ($employee->regu !== null ? ScheduleGroupType::Shift : ScheduleGroupType::NonShift)->value,
            ], ['input_by' => $userId]);

            $schedule->entries()->create(['employee_id' => $employee->id, 'work_date' => $date->toDateString(), ...$values]);
        }

        return ['employee_id' => $employee->id, 'date' => $date->toDateString(), 'from' => $from, 'to' => $code->code];
    }

    private function entryOn(Employee $employee, CarbonInterface|string $date, ?int $unitId = null): ?WorkScheduleEntry
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return WorkScheduleEntry::query()
            ->where('employee_id', $employee->id)
            ->when($unitId !== null, fn ($query) => $query->whereHas('schedule', fn ($schedule) => $schedule->where('unit_id', $unitId)))
            ->whereDate('work_date', $date)
            ->with('attendanceCode')
            ->first();
    }
}
