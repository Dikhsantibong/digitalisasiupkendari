<?php

namespace App\Services\Operator;

use App\Enums\AttendanceRequestType;
use App\Enums\ScheduleGroupType;
use App\Models\AttendanceCode;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
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
 * - Tukar shift: the employee's working shift on `start_date` goes to the
 *   pengganti (who must be free that day) and the employee gets OFF. With a
 *   `swap_date` the employee pays it back: the pengganti's shift that day goes
 *   to the employee. The rules are checked when submitting and again when
 *   approving, since the schedule may have changed in between.
 *
 * Every changed cell carries attendance_request_id and a note, and is kept in
 * `applied` with its previous code, so "Isi Otomatis" leaves it alone and
 * cancelling the approval puts the previous codes back.
 */
class AttendanceRequestService
{
    /** Longest izin / sakit / cuti range, in days. */
    public const MAX_DAYS = 31;

    public function __construct(private readonly AttendanceRoster $roster) {}

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

    /**
     * Check a new request: the range, the overlap with the employee's other
     * open or approved requests and, for a tukar shift, the two schedules.
     *
     * @param  array{type: AttendanceRequestType, start_date: string, end_date: string, substitute: Employee|null, swap_date: string|null}  $data
     *
     * @throws ValidationException
     */
    public function validateNew(Unit $unit, Employee $employee, array $data): void
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end_date' => 'Pengajuan paling lama '.self::MAX_DAYS.' hari.']);
        }

        $overlap = AttendanceRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['menunggu_pengganti', 'menunggu_persetujuan', 'disetujui'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'Sudah ada pengajuan Anda yang menunggu atau disetujui pada tanggal tersebut.']);
        }

        if ($data['type'] === AttendanceRequestType::TukarShift) {
            $substitute = $data['substitute'];
            if ($substitute === null || $substitute->id === $employee->id || ! $this->onRoster($unit, $substitute)) {
                throw ValidationException::withMessages(['substitute_employee_id' => 'Pilih rekan pengganti dari jadwal shift unit ini.']);
            }

            $this->checkSwap($employee, $substitute, $start, $data['swap_date'] !== null ? Carbon::parse($data['swap_date']) : null);
        }
    }

    /**
     * Write an approved request on the Jadwal Shift.
     *
     * @throws ValidationException when a tukar shift no longer fits the schedule
     */
    public function apply(AttendanceRequest $request, int $userId): void
    {
        $request->loadMissing(['employee', 'substitute', 'unit']);
        $codes = AttendanceCode::query()->where('is_active', true)->get()->keyBy('code');

        DB::transaction(function () use ($request, $userId, $codes): void {
            $applied = [];
            $label = "{$request->type->label()} (pengajuan #{$request->id})";

            if ($request->type === AttendanceRequestType::TukarShift) {
                [$given, $returned] = $this->checkSwap($request->employee, $request->substitute, $request->start_date, $request->swap_date);
                $off = $codes->get('OFF') ?? throw ValidationException::withMessages(['status' => 'Kode OFF belum ada di master kode absensi.']);
                $requester = $request->employee;
                $substitute = $request->substitute;

                $applied[] = $this->put($request, $requester, $request->start_date, $off, "Digantikan {$substitute->name} — {$label}", $userId);
                $applied[] = $this->put($request, $substitute, $request->start_date, $given, "Menggantikan {$requester->name} — {$label}", $userId);

                if ($request->swap_date !== null && $returned !== null) {
                    $applied[] = $this->put($request, $substitute, $request->swap_date, $off, "Digantikan {$requester->name} — balasan {$label}", $userId);
                    $applied[] = $this->put($request, $requester, $request->swap_date, $returned, "Menggantikan {$substitute->name} — balasan {$label}", $userId);
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
     * A tukar shift fits when the employee works on the date and the
     * pengganti is free; with a swap date, the other way round on that day.
     *
     * @return array{0: AttendanceCode, 1: AttendanceCode|null} the shift handed over, and the one paid back
     *
     * @throws ValidationException
     */
    private function checkSwap(Employee $employee, ?Employee $substitute, CarbonInterface $date, ?CarbonInterface $swapDate): array
    {
        $given = $this->codeOn($employee, $date);
        if ($given === null || ! $given->hitung_hadir) {
            throw ValidationException::withMessages(['start_date' => 'Pada '.$date->format('d/m/Y').' Anda tidak terjadwal shift kerja, jadi tidak ada shift yang ditukar.']);
        }

        if ($substitute === null) {
            throw ValidationException::withMessages(['substitute_employee_id' => 'Rekan pengganti tidak ditemukan.']);
        }

        $busy = $this->codeOn($substitute, $date);
        if ($busy !== null && $busy->hitung_hadir) {
            throw ValidationException::withMessages(['substitute_employee_id' => "{$substitute->name} sudah terjadwal shift {$busy->code} pada ".$date->format('d/m/Y').'.']);
        }

        if ($swapDate === null) {
            return [$given, null];
        }

        if ($swapDate->isSameDay($date)) {
            throw ValidationException::withMessages(['swap_date' => 'Tanggal balasan harus berbeda dari tanggal shift yang ditukar.']);
        }

        $returned = $this->codeOn($substitute, $swapDate);
        if ($returned === null || ! $returned->hitung_hadir) {
            throw ValidationException::withMessages(['swap_date' => 'Pada '.$swapDate->format('d/m/Y')." {$substitute->name} tidak terjadwal shift kerja untuk Anda gantikan."]);
        }

        $mine = $this->codeOn($employee, $swapDate);
        if ($mine !== null && $mine->hitung_hadir) {
            throw ValidationException::withMessages(['swap_date' => "Anda sudah terjadwal shift {$mine->code} pada ".$swapDate->format('d/m/Y').'.']);
        }

        return [$given, $returned];
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
