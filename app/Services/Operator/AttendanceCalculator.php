<?php

namespace App\Services\Operator;

use App\Models\AttendanceCode;
use App\Models\WorkScheduleEntry;
use Illuminate\Support\Collection;

/**
 * The single place that turns raw schedule cells into recap figures. The
 * per-employee code counts, the attendance percentage, and the per-code totals
 * are all derived here — never stored — so the grid, the print view, and any
 * report read the same numbers.
 *
 * Attendance % follows the Excel definition: worked shifts (codes flagged
 * `hitung_hadir`, i.e. P/S/M) divided by the days actually scheduled — every
 * non-blank cell, OFF included. A clean 8-day rotation (6 on, 2 OFF) lands at
 * 0.75; absences (C/SKT/I/A) lower it. Blank days are ignored.
 */
class AttendanceCalculator
{
    /**
     * @param  Collection<int, WorkScheduleEntry>  $entries
     * @param  Collection<int, AttendanceCode>  $codes  keyed by id
     * @return array{
     *     per_employee: array<int, array{counts: array<string, int>, present: int, scheduled: int, percent: float|null}>,
     *     totals: array<string, int>
     * }
     */
    public function summarise(Collection $entries, Collection $codes): array
    {
        $presentIds = $codes->filter(fn (AttendanceCode $c): bool => $c->hitung_hadir)->keys()->flip();
        $codeOf = $codes->map(fn (AttendanceCode $c): string => $c->code);

        $perEmployee = [];
        $totals = [];

        foreach ($entries as $entry) {
            if ($entry->attendance_code_id === null) {
                continue;
            }

            $code = $codeOf->get($entry->attendance_code_id);
            if ($code === null) {
                continue;
            }

            $employeeId = $entry->employee_id;
            $perEmployee[$employeeId] ??= ['counts' => [], 'present' => 0, 'scheduled' => 0, 'percent' => null];

            $perEmployee[$employeeId]['counts'][$code] = ($perEmployee[$employeeId]['counts'][$code] ?? 0) + 1;
            $perEmployee[$employeeId]['scheduled']++;
            if ($presentIds->has($entry->attendance_code_id)) {
                $perEmployee[$employeeId]['present']++;
            }

            $totals[$code] = ($totals[$code] ?? 0) + 1;
        }

        foreach ($perEmployee as &$summary) {
            $summary['percent'] = $summary['scheduled'] > 0
                ? round($summary['present'] / $summary['scheduled'], 4)
                : null;
        }
        unset($summary);

        return ['per_employee' => $perEmployee, 'totals' => $totals];
    }
}
