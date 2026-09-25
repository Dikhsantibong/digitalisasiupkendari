<?php

namespace App\Support;

use App\Support\HarLembar\HarLembar;

/**
 * Patrol Check Parameter Mesin (Input HAR, bulanan, per mesin): satu
 * baris per tanggal berisi PIC, jam, beban, parameter engine (temperatur
 * coolant/LO/T/C, tekanan LO), generator (temperatur bearing & winding, arus,
 * tegangan, cos phi, kVAR), trafo (oil level, temperatur) dan tegangan
 * baterai. Akhir pekan & hari libur merah (kalender {@see HarLembar::days()}).
 *
 * @phpstan-type Column array{key: string, label: string, type: 'text'|'number', path: list<string>, width: int}
 * @phpstan-type HeaderCell array{label: string, colspan: int, rowspan: int}
 */
final class HarPatrolCheckParameter
{
    public const TITLE = 'Patrol Check Parameter Mesin';

    /** Header depth: up to three group levels above the column label. */
    public const HEADER_DEPTH = 4;

    /** Width (relative units) of the TGL column. */
    public const DAY_WIDTH = 26;

    /**
     * @return list<Column>
     */
    public static function columns(): array
    {
        $engineTemp = ['ENGINE', 'TEMPERATURE (C)'];
        $genTemp = ['GENERATOR', 'TEMPERATURE (C)'];

        return [
            self::col('pic', 'PIC', 'text', [], 60),
            self::col('jam', 'JAM', 'text', [], 40),
            self::col('load', 'LOAD (KW)', 'number', [], 44),
            self::col('coolant_1', '1', 'number', [...$engineTemp, 'COOLANT'], 26),
            self::col('coolant_2', '2', 'number', [...$engineTemp, 'COOLANT'], 26),
            self::col('lo_temp', 'LO', 'number', $engineTemp, 26),
            self::col('tc_l', 'L', 'number', [...$engineTemp, 'T/C'], 30),
            self::col('tc_r', 'R', 'number', [...$engineTemp, 'T/C'], 30),
            self::col('lo_press', 'LO', 'number', ['ENGINE', 'PRESS.'], 36),
            self::col('bearing', 'BEARING', 'number', $genTemp, 36),
            self::col('winding_l1', 'L1', 'number', [...$genTemp, 'WINDING'], 36),
            self::col('winding_l2', 'L2', 'number', [...$genTemp, 'WINDING'], 36),
            self::col('winding_l3', 'L3', 'number', [...$genTemp, 'WINDING'], 36),
            self::col('ampere_r', 'R', 'number', ['GENERATOR', 'AMPERE (A)'], 36),
            self::col('ampere_s', 'S', 'number', ['GENERATOR', 'AMPERE (A)'], 36),
            self::col('ampere_t', 'T', 'number', ['GENERATOR', 'AMPERE (A)'], 36),
            self::col('voltage_r', 'R', 'number', ['GENERATOR', 'VOLTAGE (VAC)'], 36),
            self::col('voltage_s', 'S', 'number', ['GENERATOR', 'VOLTAGE (VAC)'], 36),
            self::col('voltage_t', 'T', 'number', ['GENERATOR', 'VOLTAGE (VAC)'], 36),
            self::col('cos_phi', 'COS PHI', 'number', ['GENERATOR'], 36),
            self::col('kvar', 'KVAR', 'number', ['GENERATOR'], 36),
            self::col('oil_level', 'OIL LEVEL', 'text', ['TRAFO'], 42),
            self::col('trafo_temp', 'TEMP. (C)', 'number', ['TRAFO'], 32),
            self::col('volt_battery', 'VOLT. BATTERY (VDC)', 'number', [], 38),
        ];
    }

    /**
     * The multi-level header (TGL first): groups span their columns, a column
     * label spans the remaining levels below its groups.
     *
     * @return list<list<HeaderCell>>
     */
    public static function headerRows(): array
    {
        $columns = self::columns();
        $rows = array_fill(0, self::HEADER_DEPTH, []);
        $rows[0][] = ['label' => 'TGL', 'colspan' => 1, 'rowspan' => self::HEADER_DEPTH];

        for ($level = 0; $level < self::HEADER_DEPTH; $level++) {
            $index = 0;
            while ($index < count($columns)) {
                $path = $columns[$index]['path'];

                if (count($path) === $level) {
                    $rows[$level][] = ['label' => $columns[$index]['label'], 'colspan' => 1, 'rowspan' => self::HEADER_DEPTH - $level];
                    $index++;

                    continue;
                }

                if (count($path) < $level) {
                    $index++;

                    continue;
                }

                $prefix = array_slice($path, 0, $level + 1);
                $span = 0;
                while ($index + $span < count($columns) && array_slice($columns[$index + $span]['path'], 0, $level + 1) === $prefix && count($columns[$index + $span]['path']) > $level) {
                    $span++;
                }

                $rows[$level][] = ['label' => $path[$level], 'colspan' => $span, 'rowspan' => 1];
                $index += $span;
            }
        }

        return $rows;
    }

    /**
     * Column widths as percentages of the table (TGL first), so the sheet fits
     * any page margin.
     *
     * @return list<float>
     */
    public static function widthPercents(): array
    {
        $widths = [self::DAY_WIDTH, ...array_column(self::columns(), 'width')];
        $total = array_sum($widths);

        return array_map(fn (int $width): float => round($width / $total * 100, 2), $widths);
    }

    /**
     * Trimmed values of one day, numbers with a decimal comma normalised to a
     * dot; blanks dropped.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public static function sanitize(array $values): array
    {
        $clean = [];
        foreach (self::columns() as $column) {
            $value = trim((string) ($values[$column['key']] ?? ''));
            if ($value === '') {
                continue;
            }

            $clean[$column['key']] = $column['type'] === 'number' ? str_replace(',', '.', $value) : $value;
        }

        return $clean;
    }

    /**
     * @return array{title: string, columns: list<Column>, header_rows: list<list<HeaderCell>>, widths: list<float>}
     */
    public static function toArray(): array
    {
        return [
            'title' => self::TITLE,
            'columns' => self::columns(),
            'header_rows' => self::headerRows(),
            'widths' => self::widthPercents(),
        ];
    }

    /**
     * @param  'text'|'number'  $type
     * @param  list<string>  $path
     * @return Column
     */
    private static function col(string $key, string $label, string $type, array $path, int $width): array
    {
        return compact('key', 'label', 'type', 'path', 'width');
    }
}
