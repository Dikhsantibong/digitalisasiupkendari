<?php

namespace App\Services\Dashboard;

use App\Support\Indonesian;
use Illuminate\Support\Carbon;

/**
 * Shared helpers of the dashboard modules: the KPI tile payload (value, change
 * against the previous month, trend line) and the six-month window the trend
 * charts cover.
 */
class DashboardSupport
{
    /**
     * A KPI tile. `delta` is the change in percent against `previous`, null
     * when there is nothing to compare with (no figure last month).
     *
     * @param  list<float|int>  $spark
     * @return array{key: string, label: string, value: float|int|null, unit: string|null, format: string, delta: float|null, good: string, spark: list<float>}
     */
    public static function kpi(string $key, string $label, float|int|null $value, ?string $unit, string $format, string $good, float|int|null $previous = null, array $spark = []): array
    {
        $delta = $value === null || $previous === null || (float) $previous === 0.0
            ? null
            : round(((float) $value - (float) $previous) / abs((float) $previous) * 100, 1);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'format' => $format,
            'delta' => $delta,
            'good' => $good,
            'spark' => array_map('floatval', array_values($spark)),
        ];
    }

    /**
     * The last `$count` months ending with the month of `$now`, oldest first.
     *
     * @return list<array{month: int, year: int, label: string}>
     */
    public static function months(Carbon $now, int $count = 6): array
    {
        $cursor = $now->copy()->startOfMonth()->subMonths($count - 1);
        $months = [];

        for ($i = 0; $i < $count; $i++) {
            $months[] = [
                'month' => $cursor->month,
                'year' => $cursor->year,
                'label' => mb_substr(Indonesian::monthName($cursor->month), 0, 3).' '.substr((string) $cursor->year, 2),
            ];
            $cursor->addMonth();
        }

        return $months;
    }

    /** "{year}-{month}" key of a month, for grouping rows by period. */
    public static function key(int $year, int $month): string
    {
        return $year.'-'.$month;
    }
}
