<?php

namespace App\Services\Operasi;

use App\Models\OperasiStandMeter;
use App\Models\Unit;

/**
 * The monthly Pemakaian Bahan Bakar sheet of a unit, laid out like Pemakaian
 * Pelumas but without periods: one block per jenis BBM of the unit (see
 * {@see UnitFuelTypes}), one column per mesin burning it, rows per tanggal and
 * a JMH row. Readings are keyed "{fuelCode}_{machineId}" then by day; every
 * total is computed here, never trusted from the browser.
 */
class PemakaianBbmSheet
{
    public function __construct(private readonly UnitFuelTypes $fuels) {}

    /**
     * @return list<array{code: string, name: string, material_code: string|null, machines: list<array{id: int, name: string, type: string|null, serial_number: string|null}>}>
     */
    public function columns(Unit $unit): array
    {
        return $this->fuels->forUnit($unit);
    }

    /**
     * Keep only cells of this sheet with a positive amount.
     *
     * @param  array<array-key, mixed>  $readings
     * @param  list<array{code: string, machines: list<array{id: int}>}>  $columns
     * @return array<string, array<int, float>>
     */
    public function sanitize(array $readings, array $columns, int $daysInMonth): array
    {
        $clean = [];

        foreach ($columns as $fuel) {
            foreach ($fuel['machines'] as $machine) {
                $key = $this->key($fuel['code'], $machine['id']);
                $row = $readings[$key] ?? null;

                if (! is_array($row)) {
                    continue;
                }

                foreach ($row as $day => $value) {
                    $day = (int) $day;
                    $amount = is_numeric($value) ? round((float) $value, 2) : 0.0;

                    if ($day >= 1 && $day <= $daysInMonth && $amount > 0) {
                        $clean[$key][$day] = $amount;
                    }
                }

                if (isset($clean[$key])) {
                    ksort($clean[$key]);
                }
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, array<int|string, float|int|string>>  $readings
     * @param  list<array{code: string, machines: list<array{id: int}>}>  $columns
     * @return array{totals_by_fuel: array<string, float>, totals_by_machine: array<string, float>, grand_total: float}
     */
    public function summarize(array $readings, array $columns): array
    {
        $byFuel = [];
        $byMachine = [];

        foreach ($columns as $fuel) {
            $byFuel[$fuel['code']] = 0.0;

            foreach ($fuel['machines'] as $machine) {
                $key = $this->key($fuel['code'], $machine['id']);
                $total = round(array_sum(array_map('floatval', $readings[$key] ?? [])), 2);
                $byFuel[$fuel['code']] += $total;
                $byMachine[$key] = $total;
            }

            $byFuel[$fuel['code']] = round($byFuel[$fuel['code']], 2);
        }

        return [
            'totals_by_fuel' => $byFuel,
            'totals_by_machine' => $byMachine,
            'grand_total' => round(array_sum($byFuel), 2),
        ];
    }

    /**
     * The daily PEMAKAIAN per mesin of the month's Stand Flow Meter sheets,
     * as sheet readings (only the jenis BBM / machines of this sheet).
     *
     * @param  list<array{code: string, machines: list<array{id: int}>}>  $columns
     * @return array<string, array<int, float>>
     */
    public function fromStandMeter(Unit $unit, int $month, int $year, array $columns, int $daysInMonth): array
    {
        $readings = [];
        $meters = OperasiStandMeter::query()
            ->where('unit_id', $unit->id)
            ->where('month', $month)
            ->where('year', $year)
            ->whereIn('fuel_name', array_column($columns, 'code'))
            ->get(['fuel_name', 'readings']);

        foreach ($meters as $meter) {
            foreach ($meter->readings ?? [] as $row) {
                $day = (int) ($row['tgl'] ?? 0);

                foreach ($row['machines'] ?? [] as $machineId => $cell) {
                    $readings[$this->key($meter->fuel_name, (int) ($cell['machine_id'] ?? $machineId))][$day] = (float) ($cell['pemakaian'] ?? 0);
                }
            }
        }

        return $this->sanitize($readings, $columns, $daysInMonth);
    }

    public function key(string $fuelCode, int $machineId): string
    {
        return "{$fuelCode}_{$machineId}";
    }
}
