<?php

namespace App\Services\Operator;

use App\Models\LogsheetParameter;
use Illuminate\Support\Collection;

/**
 * The logsheet table head built from a template's parameters (user,
 * 2026-11-21): up to three header levels (group_label → name → sub_channel),
 * consecutive equal labels merged like the paper form, a parameter with fewer
 * levels spanning the remaining rows. Mirrored by resources/js/lib/logsheet-layout.ts
 * for the page and the Excel export — keep the two in sync.
 */
class LogsheetLayout
{
    /**
     * @param  Collection<int, LogsheetParameter>  $parameters
     * @return list<list<array{label: string, colspan: int, rowspan: int}>>
     */
    public static function headerRows(Collection $parameters): array
    {
        $paths = $parameters->map(fn (LogsheetParameter $p): array => array_values(array_filter(
            [$p->group_label, $p->name, $p->sub_channel],
            fn (?string $label): bool => $label !== null && $label !== '',
        )))->values()->all();

        $depth = max(1, ...array_map('count', $paths ?: [[]]));
        $rows = array_fill(0, $depth, []);

        foreach (range(0, $depth - 1) as $level) {
            $previousKey = null;
            foreach ($paths as $path) {
                // The first label takes the rows the path lacks; the rest one row each.
                $extra = $depth - count($path);
                $index = $level === 0 ? 0 : $level - $extra;
                if ($index < 0 || $index >= count($path) || ($level > 0 && $index === 0)) {
                    $previousKey = null;

                    continue;
                }

                $key = json_encode(array_slice($path, 0, $index + 1));
                if ($key === $previousKey) {
                    $rows[$level][count($rows[$level]) - 1]['colspan']++;

                    continue;
                }

                $rows[$level][] = ['label' => $path[$index], 'colspan' => 1, 'rowspan' => $index === 0 ? $extra + 1 : 1];
                $previousKey = $key;
            }
        }

        return array_values(array_filter($rows, fn (array $row): bool => $row !== []));
    }

    /**
     * MIN / MAX / RATA2 of every number column over the filled hours (the
     * Logsheet Lokal 03 footer); a column without numbers stays empty.
     *
     * @param  Collection<int, LogsheetParameter>  $parameters
     * @param  list<array{time_slot: string, values: array<string, string|null>}>  $rows
     * @return list<array{label: string, values: array<string, string|null>}>
     */
    public static function summaryRows(Collection $parameters, array $rows): array
    {
        $out = ['MIN' => [], 'MAX' => [], 'RATA2' => []];
        foreach ($parameters as $parameter) {
            $key = 'p_'.$parameter->id;
            $numbers = $parameter->isNumeric()
                ? array_values(array_map('floatval', array_filter(array_column(array_column($rows, 'values'), $key), fn ($v): bool => is_numeric($v))))
                : [];
            $format = fn (?float $n): ?string => $n === null ? null : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
            $out['MIN'][$key] = $numbers === [] ? null : $format(min($numbers));
            $out['MAX'][$key] = $numbers === [] ? null : $format(max($numbers));
            $out['RATA2'][$key] = $numbers === [] ? null : $format(array_sum($numbers) / count($numbers));
        }

        return array_map(fn (string $label): array => ['label' => $label, 'values' => $out[$label]], array_keys($out));
    }

    /**
     * The reference rows printed under the head (only those the template fills).
     *
     * @param  Collection<int, LogsheetParameter>  $parameters
     * @return list<array{label: string, field: string}>
     */
    public static function referenceRows(Collection $parameters): array
    {
        return collect([
            ['label' => 'Normal', 'field' => 'normal_value'],
            ['label' => 'Min / Alarm', 'field' => 'alarm_value'],
            ['label' => 'Max / Trip', 'field' => 'trip_value'],
        ])->filter(fn (array $row): bool => $parameters->contains(fn (LogsheetParameter $p): bool => filled($p->{$row['field']})))->values()->all();
    }
}
