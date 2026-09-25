<?php

namespace App\Services\Operator;

use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\EmployeePresence;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkScheduleEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Absen masuk / absen pulang for the signed-in user's employee record. Each tap
 * must come from within the unit's office radius (set by Super Admin in menu
 * Lokasi Absensi); the distance is measured here, on the server, from the
 * position the phone reports.
 *
 * One absen masuk per work date. Absen pulang closes the latest absen masuk
 * that is still open — up to {@see self::OPEN_HOURS} hours later, so a Sore
 * shift that ends after midnight still closes the right day.
 *
 * Each absen masuk records the account's scheduled shift from Jadwal Shift
 * (P/S/M/OFF…) and the minutes late against its start. Outside a working
 * shift (OFF, cuti, or not scheduled) the absen is still allowed but needs a
 * note, e.g. "ganti shift". There is no time window.
 */
class PresenceRecorder
{
    /** Work dates follow local time at the units (WITA). */
    public const TIMEZONE = 'Asia/Makassar';

    public const OPEN_HOURS = 20;

    /** From this hour (WITA) an absen belongs to tomorrow's Malam shift, which starts at 00:00. */
    public const NIGHT_SHIFT_FROM_HOUR = 20;

    private const EARTH_RADIUS_M = 6_371_000;

    /**
     * The active employee record linked to the user, or null.
     */
    public function employeeOf(User $user): ?Employee
    {
        return Employee::query()->where('user_id', $user->id)->where('is_active', true)->with('unit')->first();
    }

    /**
     * The work date and scheduled shift an absen masuk made now belongs to:
     * today's, or tomorrow's when it is evening and tomorrow is a Malam shift.
     *
     * @return array{work_date: string, code: AttendanceCode|null}
     */
    public function currentShift(Employee $employee): array
    {
        $now = Carbon::now(self::TIMEZONE);
        $today = $now->toDateString();

        if ($now->hour >= self::NIGHT_SHIFT_FROM_HOUR) {
            $tomorrow = $now->copy()->addDay()->toDateString();
            $next = $this->scheduledCode($employee, $tomorrow);

            if ($next?->code === 'M') {
                return ['work_date' => $tomorrow, 'code' => $next];
            }
        }

        return ['work_date' => $today, 'code' => $this->scheduledCode($employee, $today)];
    }

    /**
     * The Jadwal Shift code for the employee on a date, or null when unscheduled.
     */
    public function scheduledCode(Employee $employee, string $date): ?AttendanceCode
    {
        return WorkScheduleEntry::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->whereNotNull('attendance_code_id')
            ->with('attendanceCode')
            ->first()?->attendanceCode;
    }

    /** Whether the code is a working shift (Pagi/Sore/Malam), as opposed to OFF, cuti, or absence. */
    public function isWorkingShift(?AttendanceCode $code): bool
    {
        return $code !== null && $code->hitung_hadir;
    }

    public function openPresence(Employee $employee): ?EmployeePresence
    {
        return EmployeePresence::query()
            ->where('employee_id', $employee->id)
            ->whereNull('check_out_at')
            ->where('check_in_at', '>=', now()->subHours(self::OPEN_HOURS))
            ->latest('check_in_at')
            ->first();
    }

    public function presenceOn(Employee $employee, string $workDate): ?EmployeePresence
    {
        return EmployeePresence::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->first();
    }

    public function checkIn(User $user, float $latitude, float $longitude, ?int $accuracy, ?string $note = null): EmployeePresence
    {
        [$employee, $unit] = $this->resolve($user);

        if ($this->openPresence($employee) !== null) {
            throw ValidationException::withMessages(['presence' => 'Anda belum absen pulang dari absen masuk sebelumnya.']);
        }

        ['work_date' => $workDate, 'code' => $code] = $this->currentShift($employee);

        if ($this->presenceOn($employee, $workDate) !== null) {
            throw ValidationException::withMessages(['presence' => 'Anda sudah absen masuk untuk shift ini.']);
        }

        $note = trim((string) $note);
        $working = $this->isWorkingShift($code);

        if (! $working && $note === '') {
            $reason = $code === null ? 'belum dijadwalkan' : $code->label;
            throw ValidationException::withMessages(['note' => "Jadwal Anda hari ini {$reason}. Isi catatan alasan absen, misalnya ganti shift."]);
        }

        $distance = $this->assertWithinRadius($unit, $latitude, $longitude);

        return EmployeePresence::query()->create([
            'unit_id' => $unit->id,
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'work_date' => $workDate,
            'shift_code' => $code?->code,
            'late_minutes' => $working ? $this->lateMinutes($workDate, $code) : null,
            'check_in_note' => $note === '' ? null : $note,
            'check_in_at' => now(),
            'check_in_latitude' => $latitude,
            'check_in_longitude' => $longitude,
            'check_in_distance_m' => $distance,
            'check_in_accuracy_m' => $accuracy,
        ]);
    }

    public function checkOut(User $user, float $latitude, float $longitude, ?int $accuracy): EmployeePresence
    {
        [$employee, $unit] = $this->resolve($user);

        $presence = $this->openPresence($employee);
        if ($presence === null) {
            throw ValidationException::withMessages(['presence' => 'Belum ada absen masuk yang bisa ditutup. Lakukan absen masuk terlebih dahulu.']);
        }

        $distance = $this->assertWithinRadius($unit, $latitude, $longitude);

        $presence->update([
            'check_out_at' => now(),
            'check_out_latitude' => $latitude,
            'check_out_longitude' => $longitude,
            'check_out_distance_m' => $distance,
            'check_out_accuracy_m' => $accuracy,
        ]);

        return $presence;
    }

    /**
     * Great-circle (haversine) distance in metres.
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Minutes after the shift's start on the work date (0 when on time).
     */
    private function lateMinutes(string $workDate, ?AttendanceCode $code): ?int
    {
        if ($code?->jam_mulai === null) {
            return null;
        }

        $start = Carbon::parse($workDate.' '.$code->jam_mulai, self::TIMEZONE);
        $now = Carbon::now(self::TIMEZONE);

        return $now->greaterThan($start) ? (int) $start->diffInMinutes($now) : 0;
    }

    /**
     * @return array{0: Employee, 1: Unit}
     */
    private function resolve(User $user): array
    {
        $employee = $this->employeeOf($user);

        if ($employee === null || $employee->unit === null) {
            throw ValidationException::withMessages(['presence' => 'Akun Anda belum terhubung ke data pegawai aktif. Hubungi admin.']);
        }

        if (! $employee->unit->hasAttendanceLocation()) {
            throw ValidationException::withMessages(['presence' => 'Lokasi kantor unit Anda belum diatur. Hubungi Super Admin.']);
        }

        return [$employee, $employee->unit];
    }

    private function assertWithinRadius(Unit $unit, float $latitude, float $longitude): int
    {
        $distance = (int) round(self::distanceMeters((float) $unit->latitude, (float) $unit->longitude, $latitude, $longitude));

        if ($distance > $unit->attendance_radius_m) {
            throw ValidationException::withMessages([
                'location' => "Anda berada {$distance} m dari kantor {$unit->name}. Absen hanya bisa dalam radius {$unit->attendance_radius_m} m.",
            ]);
        }

        return $distance;
    }
}
