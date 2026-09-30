<?php

namespace App\Services\Operator;

use App\Models\AttendanceCode;
use App\Models\EmployeePresence;
use App\Models\WorkScheduleEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single place that turns the Jadwal Shift (the plan) and the presensi
 * (absen masuk / pulang of the employee's account) into attendance figures.
 * Nothing here is stored, so the grid, the PDF / Excel and any report read the
 * same numbers.
 *
 * The schedule codes stay the plan. Attendance comes only from the presensi:
 * - hadir: the employee checked in for that work date (terlambat when late);
 * - tidak hadir: a working shift (a `hitung_hadir` code, i.e. P/S/M) whose date
 *   has passed without any check-in;
 * - menunggu: today's working shift not checked in yet;
 * - di luar jadwal: a check-in on a day without a working shift (OFF, blank …).
 * Attendance % = scheduled working days attended ÷ scheduled working days that
 * are due (past days, plus today once attended). OFF, cuti, sakit and izin
 * never count against it.
 */
class AttendanceCalculator
{
    public const HADIR = 'hadir';

    public const TERLAMBAT = 'terlambat';

    public const TIDAK_HADIR = 'tidak_hadir';

    public const MENUNGGU = 'menunggu';

    public const DI_LUAR_JADWAL = 'di_luar_jadwal';

    /**
     * @param  Collection<int, WorkScheduleEntry>  $entries
     * @param  Collection<int, AttendanceCode>  $codes  keyed by id
     * @param  Collection<int, EmployeePresence>|null  $presences  the month's presensi of the roster
     * @param  string|null  $today  Y-m-d (WITA); defaults to now
     * @return array{
     *     per_employee: array<int, array{counts: array<string, int>, hadir: int, terlambat: int, tidak_hadir: int, jadwal_kerja: int, di_luar_jadwal: int, percent: float|null, days: array<int, string>}>,
     *     totals: array<string, int>
     * }
     */
    public function summarise(Collection $entries, Collection $codes, ?Collection $presences = null, ?string $today = null): array
    {
        $today ??= Carbon::now(PresenceRecorder::TIMEZONE)->toDateString();
        $codeOf = $codes->map(fn (AttendanceCode $c): string => $c->code);
        $working = $codes->filter(fn (AttendanceCode $c): bool => $c->hitung_hadir)->keys()->flip();

        /** @var array<int, array<string, EmployeePresence>> $presenceOf employee → work date → presence */
        $presenceOf = [];
        foreach ($presences ?? [] as $presence) {
            $presenceOf[$presence->employee_id][$presence->work_date->toDateString()] = $presence;
        }

        $perEmployee = [];
        $totals = [];
        $blank = fn (): array => ['counts' => [], 'hadir' => 0, 'terlambat' => 0, 'tidak_hadir' => 0, 'jadwal_kerja' => 0, 'di_luar_jadwal' => 0, 'attended' => 0, 'percent' => null, 'days' => []];
        $seen = [];

        foreach ($entries as $entry) {
            $code = $entry->attendance_code_id === null ? null : $codeOf->get($entry->attendance_code_id);
            if ($code === null) {
                continue;
            }

            $employeeId = $entry->employee_id;
            $date = $entry->work_date->toDateString();
            $perEmployee[$employeeId] ??= $blank();
            $summary = &$perEmployee[$employeeId];
            $seen[$employeeId][$date] = true;

            $summary['counts'][$code] = ($summary['counts'][$code] ?? 0) + 1;
            $totals[$code] = ($totals[$code] ?? 0) + 1;

            $presence = $presenceOf[$employeeId][$date] ?? null;
            $status = $this->status($working->has($entry->attendance_code_id), $presence, $date, $today);
            if ($status !== null) {
                $summary['days'][(int) $entry->work_date->day] = $status;
            }

            if ($working->has($entry->attendance_code_id) && ($presence !== null || $date < $today)) {
                $summary['jadwal_kerja']++;
                $summary['attended'] += $presence !== null ? 1 : 0;
            }
            unset($summary);
        }

        // Check-ins on days with no schedule entry at all.
        foreach ($presenceOf as $employeeId => $byDate) {
            foreach ($byDate as $date => $presence) {
                if (isset($seen[$employeeId][$date])) {
                    continue;
                }
                $perEmployee[$employeeId] ??= $blank();
                $perEmployee[$employeeId]['days'][(int) substr($date, 8, 2)] = self::DI_LUAR_JADWAL;
            }
        }

        foreach ($perEmployee as &$summary) {
            foreach ($summary['days'] as $status) {
                match ($status) {
                    self::HADIR => $summary['hadir']++,
                    self::TERLAMBAT => [$summary['hadir']++, $summary['terlambat']++],
                    self::DI_LUAR_JADWAL => [$summary['hadir']++, $summary['di_luar_jadwal']++],
                    self::TIDAK_HADIR => $summary['tidak_hadir']++,
                    default => null,
                };
            }
            $summary['percent'] = $summary['jadwal_kerja'] > 0 ? round($summary['attended'] / $summary['jadwal_kerja'], 4) : null;
            unset($summary['attended']);
        }
        unset($summary);

        return ['per_employee' => $perEmployee, 'totals' => $totals];
    }

    /**
     * Attendance status of one scheduled day, or null when nothing applies
     * (a future day, OFF / cuti without a check-in).
     */
    public function status(bool $workingShift, ?EmployeePresence $presence, string $date, string $today): ?string
    {
        if ($presence !== null) {
            if (! $workingShift) {
                return self::DI_LUAR_JADWAL;
            }

            return ($presence->late_minutes ?? 0) > 0 ? self::TERLAMBAT : self::HADIR;
        }

        if (! $workingShift) {
            return null;
        }

        return match (true) {
            $date < $today => self::TIDAK_HADIR,
            $date === $today => self::MENUNGGU,
            default => null,
        };
    }
}
