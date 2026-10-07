<?php

namespace App\Services\Operasi;

use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperasiPemakaianPelumas;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * The monthly Pemakaian Pelumas sheet of a unit, laid out like the field
 * Excel: one block per jenis pelumas (from the unit's master Jenis Pelumas),
 * one column per mesin that uses it, rows per tanggal in Periode I (1–10),
 * II (11–20) and III (21–end). Readings are keyed "{lubricantId}_{machineId}"
 * then by day; every total is computed here, never trusted from the browser.
 * Each cell is split into pelumas tambah (top-up) and pelumas ganti (oil
 * change): `raw_readings` holds the total, `readings_ganti` the ganti part.
 */
class PemakaianPelumasSheet
{
    /**
     * Lubricant blocks of the unit. A lubricant linked to machines in the
     * master (Master Mesin → pelumas) only shows those; otherwise every
     * active machine of the unit.
     *
     * @return list<array{id: int, name: string, code: string|null, unit_of_measure: string, unit_label: string, machines: list<array{id: int, name: string}>}>
     */
    public function columns(Unit $unit): array
    {
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return LubricantType::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->with(['machines' => fn ($query) => $query->where('machines.unit_id', $unit->id)->where('machines.is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (LubricantType $lubricant) use ($machines): array {
                $linked = $lubricant->machines->pluck('id')->all();
                $used = $linked === [] ? $machines : $machines->whereIn('id', $linked);

                return [
                    'id' => $lubricant->id,
                    'name' => $lubricant->name,
                    'code' => $lubricant->code,
                    'unit_of_measure' => $lubricant->unit_of_measure?->value ?? 'liter',
                    'unit_label' => $lubricant->unit_of_measure?->label() ?? 'Liter',
                    'machines' => $used->map(fn (Machine $machine): array => ['id' => $machine->id, 'name' => $machine->name])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, from: int, to: int}>
     */
    public function periods(int $daysInMonth): array
    {
        return [
            ['key' => 'p1', 'label' => 'PERIODE I', 'from' => 1, 'to' => 10],
            ['key' => 'p2', 'label' => 'PERIODE II', 'from' => 11, 'to' => 20],
            ['key' => 'p3', 'label' => 'PERIODE III', 'from' => 21, 'to' => $daysInMonth],
        ];
    }

    /**
     * Pelumas Tambah & Ganti of a month: the unit's active machines grouped
     * by merk, the jenis pelumas, and per "{lubricantId}_{machineId}" → day
     * the tambah and the ganti amounts.
     *
     * @return array{
     *     groups: list<array{merk: string, machines: list<array{id: int, name: string, type: string|null, serial_number: string|null}>}>,
     *     lubricants: list<array{id: int, name: string, code: string|null, unit_label: string}>,
     *     tambah: array<string, array<int, float>>,
     *     ganti: array<string, array<int, float>>,
     *     days_in_month: int
     * }
     */
    public function tambahGanti(Unit $unit, int $month, int $year): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $columns = $this->columns($unit);
        $record = OperasiPemakaianPelumas::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();
        $total = $this->sanitize($record?->raw_readings ?? [], $columns, $daysInMonth);
        $ganti = $this->sanitize($record?->readings_ganti ?? [], $columns, $daysInMonth);

        $groups = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'merk', 'type', 'serial_number'])
            ->groupBy(fn (Machine $machine): string => strtoupper(trim((string) $machine->merk)) ?: 'MESIN')
            ->map(fn ($machines, string $merk): array => [
                'merk' => $merk,
                'machines' => $machines->map(fn (Machine $machine): array => $machine->only(['id', 'name', 'type', 'serial_number']))->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'lubricants' => array_map(fn (array $column): array => array_intersect_key($column, array_flip(['id', 'name', 'code', 'unit_label'])), $columns),
            'tambah' => $this->tambahOf($total, $ganti),
            'ganti' => $ganti,
            'days_in_month' => $daysInMonth,
        ];
    }

    /**
     * The total per cell: pelumas tambah + pelumas ganti.
     *
     * @param  array<string, array<int, float>>  $tambah
     * @param  array<string, array<int, float>>  $ganti
     * @return array<string, array<int, float>>
     */
    public function combine(array $tambah, array $ganti): array
    {
        $total = $tambah;

        foreach ($ganti as $key => $days) {
            foreach ($days as $day => $amount) {
                $total[$key][$day] = round(($total[$key][$day] ?? 0) + $amount, 2);
            }

            ksort($total[$key]);
        }

        return $total;
    }

    /**
     * The pelumas tambah per cell: the total minus the ganti part (a sheet
     * saved before tambah / ganti were split is all tambah).
     *
     * @param  array<string, array<int, float>>  $total
     * @param  array<string, array<int, float>>  $ganti
     * @return array<string, array<int, float>>
     */
    public function tambahOf(array $total, array $ganti): array
    {
        $tambah = [];

        foreach ($total as $key => $days) {
            foreach ($days as $day => $amount) {
                $rest = round($amount - (float) ($ganti[$key][$day] ?? 0), 2);

                if ($rest > 0) {
                    $tambah[$key][$day] = $rest;
                }
            }
        }

        return $tambah;
    }

    /**
     * Keep only cells of this sheet (known lubricant × machine, day within the
     * month) with a positive amount.
     *
     * @param  array<array-key, mixed>  $readings
     * @param  list<array{id: int, machines: list<array{id: int, name: string}>}>  $columns
     * @return array<string, array<int, float>>
     */
    public function sanitize(array $readings, array $columns, int $daysInMonth): array
    {
        $clean = [];

        foreach ($columns as $lubricant) {
            foreach ($lubricant['machines'] as $machine) {
                $key = $this->key($lubricant['id'], $machine['id']);
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
     * Per-period, per-machine and grand totals of the sheet.
     *
     * @param  array<string, array<int|string, float|int|string>>  $readings
     * @param  list<array{id: int, name: string, machines: list<array{id: int, name: string}>}>  $columns
     * @return array{items: list<array{lubricant_type_id: int, lubricant_name: string, machine_id: int, machine_name: string, daily_readings: array<int, float>, subtotal_p1: float, subtotal_p2: float, subtotal_p3: float, total_liter: float}>, totals_by_lubricant: array<int, float>, totals_by_machine: array<int, float>, grand_total: float}
     */
    public function summarize(array $readings, array $columns, int $daysInMonth): array
    {
        $items = [];
        $byLubricant = [];
        $byMachine = [];

        foreach ($columns as $lubricant) {
            $byLubricant[$lubricant['id']] = 0.0;

            foreach ($lubricant['machines'] as $machine) {
                $row = $readings[$this->key($lubricant['id'], $machine['id'])] ?? [];
                $sum = fn (int $from, int $to): float => round(array_sum(array_map(
                    fn (int $day): float => (float) ($row[$day] ?? $row[(string) $day] ?? 0),
                    $to >= $from ? range($from, $to) : [],
                )), 2);
                $total = $sum(1, $daysInMonth);

                $byLubricant[$lubricant['id']] += $total;
                $byMachine[$machine['id']] = round(($byMachine[$machine['id']] ?? 0) + $total, 2);

                if ($total > 0) {
                    $items[] = [
                        'lubricant_type_id' => $lubricant['id'],
                        'lubricant_name' => $lubricant['name'],
                        'machine_id' => $machine['id'],
                        'machine_name' => $machine['name'],
                        'daily_readings' => array_map('floatval', $row),
                        'subtotal_p1' => $sum(1, 10),
                        'subtotal_p2' => $sum(11, 20),
                        'subtotal_p3' => $sum(21, $daysInMonth),
                        'total_liter' => $total,
                    ];
                }
            }

            $byLubricant[$lubricant['id']] = round($byLubricant[$lubricant['id']], 2);
        }

        return [
            'items' => $items,
            'totals_by_lubricant' => $byLubricant,
            'totals_by_machine' => $byMachine,
            'grand_total' => round(array_sum($byLubricant), 2),
        ];
    }

    /**
     * Split the lubricant blocks into PDF tables of at most $maxColumns data
     * columns (machines + JUMLAH), like the Excel sheet printed in parts.
     *
     * @template T of array{machines: list<mixed>}
     *
     * @param  list<T>  $columns
     * @return list<list<T>>
     */
    public function chunkForPrint(array $columns, int $maxColumns = 24): array
    {
        $chunks = [];
        $current = [];
        $width = 0;

        foreach ($columns as $lubricant) {
            $span = count($lubricant['machines']) + 1;

            if ($current !== [] && $width + $span > $maxColumns) {
                $chunks[] = $current;
                $current = [];
                $width = 0;
            }

            $current[] = $lubricant;
            $width += $span;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    public function key(int $lubricantId, int $machineId): string
    {
        return "{$lubricantId}_{$machineId}";
    }
}
