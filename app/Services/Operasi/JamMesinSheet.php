<?php

namespace App\Services\Operasi;

use App\Enums\JamMesinJenis;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\OperasiJamMesin;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * The machine-hour sheets of Pengusahaan Operasi: Jam Operasi, Jam
 * Pemeliharaan (PO) and Jam Gangguan are typed in (or taken from Star-Stop)
 * per mesin per tanggal; Jam Siap Operasi is derived — 24 jam minus the three
 * of that day. Readings are keyed by machine id then day.
 */
class JamMesinSheet
{
    public const HOURS_PER_DAY = 24;

    /**
     * The active machines of the unit, in the order of the sheet.
     *
     * @return list<array{id: int, name: string, type: string|null, serial_number: string|null}>
     */
    public function machines(Unit $unit): array
    {
        return Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'serial_number'])
            ->map(fn (Machine $machine): array => [
                'id' => $machine->id,
                'name' => $machine->name,
                'type' => $machine->type,
                'serial_number' => $machine->serial_number,
            ])
            ->all();
    }

    /**
     * Keep only hours of active machines on days of the month, between 0 and
     * 24 (exclusive of 0).
     *
     * @param  array<array-key, mixed>  $readings
     * @param  list<array{id: int}>  $machines
     * @return array<int, array<int, float>>
     */
    public function sanitize(array $readings, array $machines, int $daysInMonth): array
    {
        $clean = [];

        foreach ($machines as $machine) {
            $row = $readings[$machine['id']] ?? $readings[(string) $machine['id']] ?? null;

            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $day => $value) {
                $day = (int) $day;
                $hours = is_numeric($value) ? round((float) $value, 2) : 0.0;

                if ($day >= 1 && $day <= $daysInMonth && $hours > 0) {
                    $clean[$machine['id']][$day] = min($hours, (float) self::HOURS_PER_DAY);
                }
            }

            if (isset($clean[$machine['id']])) {
                ksort($clean[$machine['id']]);
            }
        }

        return $clean;
    }

    /**
     * @param  array<int|string, array<int|string, float|int|string>>  $readings
     * @param  list<array{id: int}>  $machines
     * @return array{totals_by_machine: array<int, float>, grand_total: float}
     */
    public function summarize(array $readings, array $machines): array
    {
        $totals = [];

        foreach ($machines as $machine) {
            $totals[$machine['id']] = round(array_sum(array_map('floatval', $readings[$machine['id']] ?? [])), 2);
        }

        return ['totals_by_machine' => $totals, 'grand_total' => round(array_sum($totals), 2)];
    }

    /**
     * The saved, cleaned readings of a sheet.
     *
     * @param  list<array{id: int}>  $machines
     * @return array<int, array<int, float>>
     */
    public function readings(Unit $unit, JamMesinJenis $jenis, int $month, int $year, array $machines): array
    {
        $sheet = $this->record($unit, $jenis, $month, $year);

        return $this->sanitize($sheet?->readings ?? [], $machines, Carbon::create($year, $month, 1)->daysInMonth);
    }

    public function record(Unit $unit, JamMesinJenis $jenis, int $month, int $year): ?OperasiJamMesin
    {
        return OperasiJamMesin::query()
            ->where('unit_id', $unit->id)
            ->where('jenis', $jenis)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }

    /**
     * The hours of this category recorded in Star-Stop, per machine per day.
     *
     * @param  list<array{id: int}>  $machines
     * @return array<int, array<int, float>>
     */
    public function fromStarStop(Unit $unit, JamMesinJenis $jenis, int $month, int $year, array $machines): array
    {
        $rows = EngineStatusLog::query()
            ->where('engine_status_logs.unit_id', $unit->id)
            ->whereIn('engine_id', array_column($machines, 'id'))
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->join('unit_status_codes', 'engine_status_logs.status_code_id', '=', 'unit_status_codes.id')
            ->where('unit_status_codes.category', $jenis->statusCategory()->value)
            ->get(['engine_id', 'report_date', 'duration_minutes']);

        $readings = [];
        foreach ($rows as $row) {
            $day = (int) Carbon::parse($row->report_date)->day;
            $readings[$row->engine_id][$day] = ($readings[$row->engine_id][$day] ?? 0) + (float) $row->duration_minutes / 60;
        }

        return $this->sanitize($readings, $machines, Carbon::create($year, $month, 1)->daysInMonth);
    }

    /**
     * Jam Siap Operasi: 24 jam minus Jam Operasi, Jam Pemeliharaan and Jam
     * Gangguan of the day, per machine. A negative value means the three
     * sheets add up to more than 24 jam that day.
     *
     * @param  list<array{id: int}>  $machines
     * @return array{readings: array<int, array<int, float>>, totals_by_machine: array<int, float>, grand_total: float, over: list<array{machine_id: int, day: int}>, filled: array<string, bool>}
     */
    public function siapOperasi(Unit $unit, int $month, int $year, array $machines): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $sheets = [];
        $filled = [];

        foreach (JamMesinJenis::cases() as $jenis) {
            $sheets[$jenis->value] = $this->readings($unit, $jenis, $month, $year, $machines);
            $filled[$jenis->value] = $this->record($unit, $jenis, $month, $year) !== null;
        }

        $readings = [];
        $over = [];
        foreach ($machines as $machine) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $used = array_sum(array_map(fn (array $sheet): float => (float) ($sheet[$machine['id']][$day] ?? 0), $sheets));
                $readings[$machine['id']][$day] = round(self::HOURS_PER_DAY - $used, 2);

                if ($used > self::HOURS_PER_DAY) {
                    $over[] = ['machine_id' => $machine['id'], 'day' => $day];
                }
            }
        }

        $totals = array_map(fn (array $row): float => round(array_sum($row), 2), $readings);

        return [
            'readings' => $readings,
            'totals_by_machine' => $totals,
            'grand_total' => round(array_sum($totals), 2),
            'over' => $over,
            'filled' => $filled,
        ];
    }
}
