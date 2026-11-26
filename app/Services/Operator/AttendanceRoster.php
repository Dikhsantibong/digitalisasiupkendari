<?php

namespace App\Services\Operator;

use App\Enums\EmployeePosition;
use App\Enums\ScheduleGroupType;
use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who sits on the absensi sheet and how their month is pre-filled.
 *
 * - Kerja Shift: operators, i.e. active employees with a regu (A–D, set in the
 *   Pegawai form, one of them the regu's Leader Shift) rotating
 *   through the per-regu {@see ShiftPattern} (default OFF,OFF,S,S,P,P,M,M).
 * - Non Shift: the unit's Project Leader and Koordinator/Office jabatan in
 *   {@see self::NON_SHIFT_POSITIONS}, then the Harmes / Harlist maintenance
 *   staff, on office hours (P on weekdays, OFF on weekends and national
 *   holidays).
 *
 * Shared by the absensi page and the absensi report so both list the same rows.
 */
class AttendanceRoster
{
    /** The shift regu an operator can belong to. */
    public const REGU = ['A', 'B', 'C', 'D'];

    /** Jabatan on the Non Shift roster, in display order. */
    public const NON_SHIFT_POSITIONS = [
        EmployeePosition::ProjectLeader,
        EmployeePosition::KoordinatorOperasi,
        EmployeePosition::KoordinatorPemeliharaan,
        EmployeePosition::KoordinatorK3,
        EmployeePosition::OfficeK3,
    ];

    private const DEFAULT_CYCLE = ['OFF', 'OFF', 'S', 'S', 'P', 'P', 'M', 'M'];

    /** Day-1 phase of each regu in the default cycle, so the four regu never share a shift. */
    private const DEFAULT_PHASES = ['A' => 0, 'B' => 6, 'C' => 2, 'D' => 4];

    /**
     * @return EloquentCollection<int, Employee>
     */
    public function employees(Unit $unit, ScheduleGroupType $group): EloquentCollection
    {
        $query = Employee::query()->where('unit_id', $unit->id)->where('is_active', true);

        if ($group === ScheduleGroupType::Shift) {
            return $query->whereNotNull('regu')->orderBy('regu')->orderByDesc('is_shift_leader')->orderBy('name')
                ->get(['id', 'name', 'nip', 'position', 'regu', 'is_shift_leader']);
        }

        $order = array_flip([
            ...array_map(fn (EmployeePosition $p): string => $p->value, self::NON_SHIFT_POSITIONS),
            Employee::POSITION_HARMES,
            Employee::POSITION_HARLIST,
            Employee::POSITION_ASME,
        ]);
        $fallbackOrder = count($order);

        return $query->whereNull('regu')
            ->with('user')
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'regu', 'is_shift_leader', 'user_id', 'division'])
            ->filter(fn (Employee $e): bool => isset($order[(string) $e->position])
                || Employee::isHarFieldPosition($e->position)
                || ($e->user !== null && $e->user->isHarFieldAccount()))
            ->sortBy(fn (Employee $e): int => $order[(string) $e->position] ?? $fallbackOrder)
            ->values();
    }

    /**
     * Each regu's rotation, from the unit's shift-pattern master or the default cycle.
     *
     * @return Collection<string, list<string>>
     */
    public function shiftSequences(Unit $unit): Collection
    {
        $patterns = ShiftPattern::query()->where('unit_id', $unit->id)->where('is_active', true)->get()
            ->mapWithKeys(fn (ShiftPattern $p): array => [$p->regu => $p->codes()])
            ->filter(fn (array $codes): bool => $codes !== []);

        if ($patterns->isEmpty()) {
            return collect(self::DEFAULT_PHASES)->map(fn (int $offset): array => $this->rotate(self::DEFAULT_CYCLE, $offset));
        }

        // A regu missing from the master (e.g. only A–C set while regu D has operators) still rotates:
        // the default cycle at the phase that spreads the shifts most evenly over the regu that have one.
        foreach (self::REGU as $regu) {
            if (! $patterns->has($regu)) {
                $patterns->put($regu, $this->rotate(self::DEFAULT_CYCLE, $this->balancedPhase($patterns->values()->all())));
            }
        }

        return $patterns->sortKeys();
    }

    /**
     * @param  list<string>  $sequence
     * @return list<string>
     */
    private function rotate(array $sequence, int $offset): array
    {
        return array_merge(array_slice($sequence, $offset), array_slice($sequence, 0, $offset));
    }

    /**
     * The default-cycle phase that, added to the given sequences, least often
     * puts two regu on the same shift on the same day.
     *
     * @param  list<list<string>>  $sequences
     */
    private function balancedPhase(array $sequences): int
    {
        $length = count(self::DEFAULT_CYCLE);
        $best = 0;
        $bestScore = PHP_INT_MAX;

        foreach ([...array_values(self::DEFAULT_PHASES), ...range(0, $length - 1)] as $phase) {
            $candidate = $this->rotate(self::DEFAULT_CYCLE, $phase);
            $score = 0;

            for ($day = 0; $day < $length; $day++) {
                foreach ($sequences as $sequence) {
                    if ($sequence !== [] && $sequence[$day % count($sequence)] === $candidate[$day] && $candidate[$day] !== 'OFF') {
                        $score++;
                    }
                }
            }

            if ($score < $bestScore) {
                [$best, $bestScore] = [$phase, $score];
            }
        }

        return $best;
    }

    /**
     * The month's codes carrying on the schedule the user started (user,
     * 2026-11-26: "isi jadwal awal saja, sistem menyesuaikan polanya"). The
     * seed — days already filled for the employee — is matched against every
     * rotation of the regu's sequence; when one fits, the whole month follows
     * it. A seed that fits no rotation but is a run of at least two days
     * (e.g. a custom P,S,M,OFF) repeats as its own cycle. Codes outside the
     * cycle (Cuti, Sakit …) are ignored for the match. Null when the seed says
     * nothing about the rotation.
     *
     * @param  list<string>  $sequence
     * @param  array<int, string>  $seed  day => code
     * @return list<string>|null
     */
    public function planFromSeed(array $sequence, array $seed, int $daysInMonth): ?array
    {
        $length = count($sequence);
        $relevant = array_filter($seed, fn (string $code): bool => in_array($code, ['P', 'S', 'M', 'OFF'], true));
        ksort($relevant);

        if ($relevant === [] || $length === 0) {
            return null;
        }

        for ($offset = 0; $offset < $length; $offset++) {
            $fits = true;

            foreach ($relevant as $day => $code) {
                if ($sequence[($offset + $day - 1) % $length] !== $code) {
                    $fits = false;
                    break;
                }
            }

            if ($fits) {
                return array_map(fn (int $day): string => $sequence[($offset + $day - 1) % $length], range(1, $daysInMonth));
            }
        }

        // Not the regu's rotation: the user's own run of days, repeated.
        $first = array_key_first($relevant);
        $run = [];

        for ($day = $first; isset($relevant[$day]); $day++) {
            $run[] = $relevant[$day];
        }

        if (count($run) < 2) {
            return null;
        }

        $period = count($run);

        for ($p = 1; $p < count($run); $p++) {
            $repeats = true;

            foreach ($run as $i => $code) {
                if ($run[$i % $p] !== $code) {
                    $repeats = false;
                    break;
                }
            }

            if ($repeats) {
                $period = $p;
                break;
            }
        }

        $cycle = array_slice($run, 0, $period);

        return array_map(
            fn (int $day): string => $cycle[((($day - $first) % $period) + $period) % $period],
            range(1, $daysInMonth),
        );
    }

    /**
     * The month's codes for one shift employee. When the last two codes of the
     * previous month identify a unique point in the cycle, the rotation carries
     * on from there; otherwise day 1 starts the regu's sequence.
     *
     * @param  list<string>  $sequence
     * @param  list<string>  $previousTail  the previous month's last codes, oldest first
     * @return list<string>
     */
    public function planShift(array $sequence, array $previousTail, int $daysInMonth): array
    {
        $length = count($sequence);
        $start = 0;

        if (count($previousTail) >= 2) {
            [$before, $last] = array_slice($previousTail, -2);
            $matches = array_values(array_filter(
                range(0, $length - 1),
                fn (int $i): bool => $sequence[($i - 1 + $length) % $length] === $before && $sequence[$i] === $last,
            ));

            if (count($matches) === 1) {
                $start = ($matches[0] + 1) % $length;
            }
        }

        return array_map(fn (int $day): string => $sequence[($start + $day - 1) % $length], range(1, $daysInMonth));
    }

    /**
     * Office-hours codes for a Non Shift employee: P on working days, OFF on
     * Saturdays, Sundays, and the given national holidays.
     *
     * @param  list<int>  $holidayDays
     * @return list<string>
     */
    public function planNonShift(int $year, int $month, array $holidayDays): array
    {
        $days = Carbon::create($year, $month, 1)->daysInMonth;

        return array_map(function (int $day) use ($year, $month, $holidayDays): string {
            $date = Carbon::create($year, $month, $day);

            return $date->isWeekend() || in_array($day, $holidayDays, true) ? 'OFF' : 'P';
        }, range(1, $days));
    }
}
