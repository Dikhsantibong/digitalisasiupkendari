<?php

namespace App\Services\Operasi;

use App\Enums\PersediaanJenis;
use App\Models\OperasiPemakaianBbm;
use App\Models\OperasiPemakaianPelumas;
use App\Models\OperasiPersediaan;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * The monthly Persediaan Bahan Bakar / Pelumas sheet of a unit: one block per
 * jenis BBM ({@see UnitFuelTypes}) or jenis pelumas (master Jenis Pelumas),
 * rows per tanggal with PERSEDIAAN AWAL, PENERIMAAN, PEMAKAIAN, the
 * PENGIRIMAN columns of the jenis and SALDO AKHIR, then the periods and TOTAL.
 * BBM: Periode I (1–7), II (8–14), III (15–22), IV (23–end); pelumas: the
 * three periods of Pemakaian Pelumas (1–10, 11–20, 21–end).
 *
 * PEMAKAIAN always comes from the month's Pemakaian sheet; the opening stock
 * is the previous month's saldo akhir unless it was corrected here. Every
 * figure is computed here, never trusted from the browser.
 */
class PersediaanSheet
{
    /** How many earlier months a carried opening stock may follow back. */
    private const MAX_CARRY_DEPTH = 24;

    public function __construct(
        private readonly UnitFuelTypes $fuels,
        private readonly PemakaianPelumasSheet $pelumas,
    ) {}

    /**
     * The blocks of the sheet: a jenis BBM (key = its code) or a jenis pelumas
     * (key = its id).
     *
     * @return list<array{key: string, name: string, code: string|null, unit_label: string, unit_of_measure: string}>
     */
    public function items(Unit $unit, PersediaanJenis $jenis): array
    {
        if ($jenis === PersediaanJenis::Bbm) {
            return array_map(fn (array $fuel): array => [
                'key' => $fuel['code'],
                'name' => $fuel['name'],
                'code' => $fuel['code'],
                'unit_label' => 'Liter',
                'unit_of_measure' => 'liter',
            ], $this->fuels->forUnit($unit));
        }

        return array_map(fn (array $lubricant): array => [
            'key' => (string) $lubricant['id'],
            'name' => $lubricant['name'],
            'code' => $lubricant['code'],
            'unit_label' => $lubricant['unit_label'],
            'unit_of_measure' => $lubricant['unit_of_measure'],
        ], $this->pelumas->columns($unit));
    }

    /**
     * The PENGIRIMAN / PEMINJAMAN / PENGEMBALIAN columns of the jenis.
     *
     * @return array<string, string> field => label
     */
    public function kirimColumns(PersediaanJenis $jenis): array
    {
        return match ($jenis) {
            PersediaanJenis::Bbm => ['kirim_1' => 'TUG 8 / Kembali Sewa SMP', 'kirim_2' => 'Pinjam THAS'],
            PersediaanJenis::Pelumas => ['kirim_1' => 'TUG 8', 'kirim_2' => 'TUG 10', 'kirim_3' => 'Over Flow'],
        };
    }

    /**
     * The typed-in daily fields of the jenis.
     *
     * @return list<string>
     */
    public function fields(PersediaanJenis $jenis): array
    {
        return ['penerimaan', ...array_keys($this->kirimColumns($jenis))];
    }

    /**
     * @return list<array{key: string, label: string, from: int, to: int}>
     */
    public function periods(PersediaanJenis $jenis, int $daysInMonth): array
    {
        if ($jenis === PersediaanJenis::Pelumas) {
            return $this->pelumas->periods($daysInMonth);
        }

        return [
            ['key' => 'p1', 'label' => 'PERIODE I', 'from' => 1, 'to' => 7],
            ['key' => 'p2', 'label' => 'PERIODE II', 'from' => 8, 'to' => 14],
            ['key' => 'p3', 'label' => 'PERIODE III', 'from' => 15, 'to' => 22],
            ['key' => 'p4', 'label' => 'PERIODE IV', 'from' => 23, 'to' => $daysInMonth],
        ];
    }

    public function record(Unit $unit, PersediaanJenis $jenis, int $month, int $year): ?OperasiPersediaan
    {
        return OperasiPersediaan::query()
            ->where('unit_id', $unit->id)
            ->where('jenis', $jenis)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }

    /**
     * Daily PEMAKAIAN per item, summed over the machines of the Pemakaian sheet.
     *
     * @param  list<array{key: string}>  $items
     * @return array<string, array<int, float>>
     */
    public function pemakaian(Unit $unit, PersediaanJenis $jenis, int $month, int $year, array $items): array
    {
        $model = $jenis === PersediaanJenis::Bbm ? OperasiPemakaianBbm::class : OperasiPemakaianPelumas::class;
        $readings = $model::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first()?->raw_readings ?? [];
        $usage = [];

        foreach ($items as $item) {
            $prefix = $item['key'].'_';

            foreach ($readings as $key => $days) {
                if (! str_starts_with((string) $key, $prefix) || ! is_array($days)) {
                    continue;
                }

                foreach ($days as $day => $amount) {
                    $usage[$item['key']][(int) $day] = round(($usage[$item['key']][(int) $day] ?? 0) + (float) $amount, 2);
                }
            }
        }

        return $usage;
    }

    /**
     * The opening stock per item carried from the previous month's saldo
     * akhir (null when that month has no sheet).
     *
     * @param  list<array{key: string}>  $items
     * @return array<string, float|null>
     */
    public function carriedOpening(Unit $unit, PersediaanJenis $jenis, int $month, int $year, array $items, int $depth = 0): array
    {
        $previous = Carbon::create($year, $month, 1)->subMonth();
        $record = $depth < self::MAX_CARRY_DEPTH ? $this->record($unit, $jenis, $previous->month, $previous->year) : null;

        if ($record === null) {
            return array_fill_keys(array_column($items, 'key'), null);
        }

        $opening = $this->openingOf($record, $this->carriedOpening($unit, $jenis, $previous->month, $previous->year, $items, $depth + 1), $items);
        $sheet = $this->compute($jenis, $items, $opening, $record->entries ?? [], $this->pemakaian($unit, $jenis, $previous->month, $previous->year, $items), $previous->daysInMonth);

        return array_map(fn (array $block): float => $block['total']['akhir'], $sheet);
    }

    /**
     * The effective opening stock: the correction stored on the sheet, else
     * the carried saldo akhir, else 0.
     *
     * @param  array<string, float|null>  $carried
     * @param  list<array{key: string}>  $items
     * @return array<string, float>
     */
    public function openingOf(?OperasiPersediaan $record, array $carried, array $items): array
    {
        $opening = [];

        foreach ($items as $item) {
            $stored = $record?->opening[$item['key']] ?? null;
            $opening[$item['key']] = round((float) ($stored ?? $carried[$item['key']] ?? 0), 2);
        }

        return $opening;
    }

    /**
     * Keep only the typed-in cells of this sheet's items with a positive amount.
     *
     * @param  array<array-key, mixed>  $entries
     * @param  list<array{key: string}>  $items
     * @return array<string, array<int, array<string, float>>>
     */
    public function sanitize(PersediaanJenis $jenis, array $entries, array $items, int $daysInMonth): array
    {
        $clean = [];

        foreach ($items as $item) {
            $days = $entries[$item['key']] ?? null;

            if (! is_array($days)) {
                continue;
            }

            foreach ($days as $day => $fields) {
                $day = (int) $day;

                if ($day < 1 || $day > $daysInMonth || ! is_array($fields)) {
                    continue;
                }

                foreach ($this->fields($jenis) as $field) {
                    $amount = is_numeric($fields[$field] ?? null) ? round((float) $fields[$field], 2) : 0.0;

                    if ($amount > 0) {
                        $clean[$item['key']][$day][$field] = $amount;
                    }
                }
            }

            if (isset($clean[$item['key']])) {
                ksort($clean[$item['key']]);
            }
        }

        return $clean;
    }

    /**
     * Per item: the opening, a row per tanggal, the four periods and the total.
     * A period's (and the total's) saldo akhir is the stock on its last day.
     *
     * @param  list<array{key: string}>  $items
     * @param  array<string, float>  $opening
     * @param  array<array-key, mixed>  $entries
     * @param  array<string, array<int, float>>  $pemakaian
     * @return array<string, array{opening: float, rows: array<int, array<string, float>>, periods: list<array<string, float|string>>, total: array<string, float>}>
     */
    public function compute(PersediaanJenis $jenis, array $items, array $opening, array $entries, array $pemakaian, int $daysInMonth): array
    {
        $sheet = [];
        $fields = $this->fields($jenis);
        $kirim = array_keys($this->kirimColumns($jenis));

        foreach ($items as $item) {
            $key = $item['key'];
            $stock = $opening[$key] ?? 0.0;
            $rows = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $row = ['awal' => round($stock, 2)];

                foreach ($fields as $field) {
                    $row[$field] = round((float) ($entries[$key][$day][$field] ?? 0), 2);
                }

                $row['pemakaian'] = round((float) ($pemakaian[$key][$day] ?? 0), 2);
                $stock = $stock + $row['penerimaan'] - $row['pemakaian'];

                foreach ($kirim as $field) {
                    $stock -= $row[$field];
                }

                $row['akhir'] = round($stock, 2);
                $rows[$day] = $row;
            }

            $sum = fn (int $from, int $to): array => $this->sum([...$fields, 'pemakaian'], array_filter($rows, fn (int $day): bool => $day >= $from && $day <= $to, ARRAY_FILTER_USE_KEY), $rows[$to]['akhir'] ?? round($opening[$key] ?? 0.0, 2));

            $sheet[$key] = [
                'opening' => round($opening[$key] ?? 0.0, 2),
                'rows' => $rows,
                'periods' => array_map(fn (array $period): array => ['key' => $period['key'], 'label' => $period['label']] + $sum($period['from'], $period['to']), $this->periods($jenis, $daysInMonth)),
                'total' => $sum(1, $daysInMonth),
            ];
        }

        return $sheet;
    }

    /**
     * @param  list<string>  $fields
     * @param  array<int, array<string, float>>  $rows
     * @return array<string, float>
     */
    private function sum(array $fields, array $rows, float $akhir): array
    {
        $total = [];

        foreach ($fields as $field) {
            $total[$field] = round(array_sum(array_column($rows, $field)), 2);
        }

        return $total + ['akhir' => $akhir];
    }
}
