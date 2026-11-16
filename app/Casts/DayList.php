<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalizes a day-of-month JSON column into a sorted unique list<int> (1..31),
 * safely accepting both JSON lists ([4, 11]) and legacy associative maps
 * ({"4": true}, {"4": "D"}, {"1": {"rencana": true, "realisasi": true}}).
 *
 * @implements CastsAttributes<list<int>, mixed>
 */
class DayList implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return self::normalize($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return json_encode([]);
        }

        return json_encode(self::normalize($value));
    }

    /**
     * @return list<int>
     */
    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            return [];
        }

        if (! is_array($decoded) || $decoded === []) {
            return [];
        }

        if (array_is_list($decoded)) {
            $days = array_map('intval', array_filter($decoded, fn ($d): bool => is_numeric($d)));

            return self::cleanDays($days);
        }

        $hasMapValues = false;
        $allUnitMarkers = true;
        foreach ($decoded as $v) {
            if (is_bool($v) || is_array($v) || (is_string($v) && ! is_numeric($v))) {
                $hasMapValues = true;
            }
            if ($v !== 1 && $v !== '1' && $v !== true) {
                $allUnitMarkers = false;
            }
        }

        $keysAreDays = ! array_key_exists(0, $decoded)
            && array_reduce(array_keys($decoded), fn (bool $carry, $k): bool => $carry && is_numeric($k) && (int) $k >= 1 && (int) $k <= 31, true);

        if ($hasMapValues || ($keysAreDays && $allUnitMarkers)) {
            $days = [];
            foreach ($decoded as $k => $v) {
                if (! is_numeric($k)) {
                    continue;
                }
                $active = is_array($v)
                    ? (! empty($v['realisasi']) || ! empty($v['rencana']))
                    : ($v === true || $v === 1 || $v === '1' || (is_string($v) && trim($v) !== ''));
                if ($active) {
                    $days[] = (int) $k;
                }
            }

            return self::cleanDays($days);
        }

        $days = array_map('intval', array_filter($decoded, fn ($d): bool => is_numeric($d)));

        return self::cleanDays($days);
    }

    /**
     * @param  array<int, int>  $days
     * @return list<int>
     */
    private static function cleanDays(array $days): array
    {
        $filtered = array_values(array_unique(array_filter($days, fn (int $d): bool => $d >= 1 && $d <= 31)));
        sort($filtered);

        return $filtered;
    }
}
