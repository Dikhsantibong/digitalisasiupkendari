<?php

namespace App\Support;

/**
 * Static SVG charts for the PDF (dompdf, as an <img> data URI) and the Excel
 * export of the Resume Bulanan — the same chart types the page draws with the
 * React charts: bar (grouped / stacked, optionally with the last series as a
 * line), line (with a threshold), donut, gauge, horizontal bar list, level
 * bars, S-curve and heatmap. Plain shapes and fixed colours only, so it renders
 * the same in dompdf and in a browser canvas.
 */
class SvgChart
{
    public const COLORS = ['#1d6fb8', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4', '#64748b', '#ec4899'];

    private const W = 680;

    private const FONT = "font-family='DejaVu Sans, Arial, sans-serif'";

    /**
     * @param  array<string, mixed>  $chart  one chart of the resume payload
     */
    public static function render(array $chart): string
    {
        $svg = match ($chart['type']) {
            'bar', 'stacked' => self::bars($chart),
            'line' => self::lines($chart),
            'donut' => self::donut($chart['data']),
            'gauge' => self::gauges($chart['items']),
            'hbar', 'level' => self::hbars($chart['data'], $chart['type'] === 'level'),
            'scurve' => self::scurve($chart['points']),
            'heatmap' => self::heatmap($chart['columns'], $chart['rows']),
            default => self::empty(),
        };

        return $svg;
    }

    public static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @param  array{labels: list<string>, series: list<array{name: string, values: list<float|int>}>, type: string, line_last?: bool}  $chart
     */
    private static function bars(array $chart): string
    {
        $labels = $chart['labels'];
        $series = $chart['series'];
        $lineLast = ($chart['line_last'] ?? false) && count($series) > 1;
        $barSeries = $lineLast ? array_slice($series, 0, -1) : $series;
        $stacked = $chart['type'] === 'stacked';
        $h = 230;
        [$left, $top, $right, $bottom] = [52, 14, $lineLast ? 46 : 12, 44];
        $plotW = self::W - $left - $right;
        $plotH = $h - $top - $bottom;
        $n = max(1, count($labels));

        $max = 0.0;
        foreach (array_keys($labels) as $i) {
            $values = array_map(fn (array $s): float => max(0, (float) ($s['values'][$i] ?? 0)), $barSeries);
            $max = max($max, $stacked ? array_sum($values) : ($values === [] ? 0 : max($values)));
        }
        $max = self::nice($max);
        $out = self::open($h).self::grid($left, $top, $plotW, $plotH, $max);
        $slot = $plotW / $n;
        $groupW = $slot * 0.7;

        foreach ($labels as $i => $label) {
            $x0 = $left + $slot * $i + ($slot - $groupW) / 2;
            $base = $top + $plotH;
            foreach ($barSeries as $s => $serie) {
                $value = max(0, (float) ($serie['values'][$i] ?? 0));
                $bh = $max > 0 ? $value / $max * $plotH : 0;
                if ($stacked) {
                    $out .= self::rect($x0, $base - $bh, $groupW, $bh, self::COLORS[$s % 8]);
                    $base -= $bh;
                } else {
                    $bw = $groupW / max(1, count($barSeries));
                    $out .= self::rect($x0 + $bw * $s, $top + $plotH - $bh, max(1, $bw - 1), $bh, self::COLORS[$s % 8]);
                }
            }
            if ($n <= 16 || $i % (int) ceil($n / 16) === 0) {
                $out .= self::text($left + $slot * $i + $slot / 2, $top + $plotH + 13, $label, 8, 'middle');
            }
        }

        if ($lineLast) {
            $line = end($series);
            $lmax = self::nice((float) max([0, ...array_map('floatval', $line['values'])]));
            $points = [];
            foreach (array_keys($labels) as $i) {
                $v = (float) ($line['values'][$i] ?? 0);
                $points[] = round($left + $slot * $i + $slot / 2, 1).','.round($top + $plotH - ($lmax > 0 ? $v / $lmax * $plotH : 0), 1);
            }
            $color = self::COLORS[(count($series) - 1) % 8];
            $out .= "<polyline points='".implode(' ', $points)."' fill='none' stroke='{$color}' stroke-width='2'/>";
            for ($k = 0; $k <= 4; $k++) {
                $out .= self::text(self::W - $right + 4, $top + $plotH - $plotH * $k / 4 + 3, self::short($lmax * $k / 4), 8, 'start');
            }
        }

        return $out.self::legend(array_column($series, 'name'), $h - 14).'</svg>';
    }

    /**
     * @param  array{labels: list<string>, series: list<array{name: string, values: list<float|int>}>, threshold?: float|null, threshold_label?: string}  $chart
     */
    private static function lines(array $chart): string
    {
        $labels = $chart['labels'];
        $h = 230;
        [$left, $top, $right, $bottom] = [52, 14, 12, 44];
        $plotW = self::W - $left - $right;
        $plotH = $h - $top - $bottom;
        $n = max(1, count($labels));
        $all = [0.0, (float) ($chart['threshold'] ?? 0)];
        foreach ($chart['series'] as $serie) {
            $all = [...$all, ...array_map('floatval', $serie['values'])];
        }
        $max = self::nice(max($all));
        $out = self::open($h).self::grid($left, $top, $plotW, $plotH, $max);
        $x = fn (int $i): float => $n === 1 ? $left + $plotW / 2 : $left + $plotW * $i / ($n - 1);
        $y = fn (float $v): float => $top + $plotH - ($max > 0 ? $v / $max * $plotH : 0);

        if (isset($chart['threshold'])) {
            $ty = round($y((float) $chart['threshold']), 1);
            $out .= "<line x1='{$left}' y1='{$ty}' x2='".($left + $plotW)."' y2='{$ty}' stroke='#ef4444' stroke-width='1' stroke-dasharray='4,3'/>";
            $out .= self::text($left + $plotW - 2, $ty - 3, ($chart['threshold_label'] ?? 'Batas').' '.self::short((float) $chart['threshold']), 8, 'end', '#ef4444');
        }

        foreach ($chart['series'] as $s => $serie) {
            $color = self::COLORS[$s % 8];
            $points = [];
            foreach (array_keys($labels) as $i) {
                $points[] = round($x($i), 1).','.round($y((float) ($serie['values'][$i] ?? 0)), 1);
            }
            $out .= "<polyline points='".implode(' ', $points)."' fill='none' stroke='{$color}' stroke-width='2'/>";
            if ($n <= 31) {
                foreach ($points as $point) {
                    [$px, $py] = explode(',', $point);
                    $out .= "<circle cx='{$px}' cy='{$py}' r='2' fill='{$color}'/>";
                }
            }
        }
        foreach ($labels as $i => $label) {
            if ($n <= 16 || $i % (int) ceil($n / 16) === 0) {
                $out .= self::text($x($i), $top + $plotH + 13, $label, 8, 'middle');
            }
        }

        return $out.self::legend(array_column($chart['series'], 'name'), $h - 14).'</svg>';
    }

    /**
     * @param  list<array{label: string, value: float|int}>  $data
     */
    private static function donut(array $data): string
    {
        $total = array_sum(array_map(fn (array $d): float => max(0, (float) $d['value']), $data));
        $h = max(180, 30 + count($data) * 20);
        $cx = 110;
        $cy = $h / 2;
        $r = 70;
        $inner = 42;
        $out = self::open($h);

        if ($total <= 0) {
            return $out.self::text(self::W / 2, $h / 2, 'Belum ada data', 11, 'middle', '#64748b').'</svg>';
        }

        $angle = -M_PI / 2;
        foreach ($data as $i => $d) {
            $value = max(0, (float) $d['value']);
            if ($value <= 0) {
                continue;
            }
            $sweep = $value / $total * 2 * M_PI;
            if ($sweep >= 2 * M_PI - 0.0001) {
                $out .= "<circle cx='{$cx}' cy='{$cy}' r='".(($r + $inner) / 2)."' fill='none' stroke='".self::COLORS[$i % 8]."' stroke-width='".($r - $inner)."'/>";
            } else {
                $out .= "<path d='".self::arcPath($cx, $cy, $r, $inner, $angle, $angle + $sweep)."' fill='".self::COLORS[$i % 8]."'/>";
            }
            $angle += $sweep;
        }
        $out .= self::text($cx, $cy + 2, self::short($total), 15, 'middle', '#0f172a', true);
        $out .= self::text($cx, $cy + 16, 'Total', 8, 'middle', '#64748b');

        foreach ($data as $i => $d) {
            $ly = $cy - count($data) * 10 + $i * 20 + 10;
            $pct = $total > 0 ? round((float) $d['value'] / $total * 100, 1) : 0;
            $out .= self::rect(220, $ly - 8, 10, 10, self::COLORS[$i % 8]);
            $out .= self::text(236, $ly, $d['label'], 9, 'start');
            $out .= self::text(self::W - 20, $ly, self::short((float) $d['value']).'  ('.str_replace('.', ',', (string) $pct).'%)', 9, 'end', '#0f172a', true);
        }

        return $out.'</svg>';
    }

    /**
     * @param  list<array{label: string, value: float, target: float, good: string}>  $items
     */
    private static function gauges(array $items): string
    {
        $h = 150;
        $out = self::open($h);
        $slot = self::W / max(1, count($items));
        foreach ($items as $i => $g) {
            $cx = $slot * $i + $slot / 2;
            $cy = 100;
            $value = min(100, max(0, (float) $g['value']));
            $ok = $g['good'] === 'up' ? $g['value'] >= $g['target'] : $g['value'] <= $g['target'];
            $out .= "<path d='".self::arcPath($cx, $cy, 70, 50, M_PI, 2 * M_PI)."' fill='#e2e8f0'/>";
            if ($value > 0) {
                $out .= "<path d='".self::arcPath($cx, $cy, 70, 50, M_PI, M_PI + M_PI * $value / 100)."' fill='".($ok ? '#10b981' : '#f59e0b')."'/>";
            }
            $ta = M_PI + M_PI * min(100, (float) $g['target']) / 100;
            $out .= "<line x1='".round($cx + 46 * cos($ta), 1)."' y1='".round($cy + 46 * sin($ta), 1)."' x2='".round($cx + 74 * cos($ta), 1)."' y2='".round($cy + 74 * sin($ta), 1)."' stroke='#0f172a' stroke-width='2'/>";
            $out .= self::text($cx, $cy - 6, str_replace('.', ',', (string) round((float) $g['value'], 2)).'%', 14, 'middle', '#0f172a', true);
            $out .= self::text($cx, $cy + 16, $g['label'], 10, 'middle', '#0f172a', true);
            $out .= self::text($cx, $cy + 30, 'Target '.($g['good'] === 'up' ? '≥ ' : '≤ ').self::short((float) $g['target']).'% · '.($ok ? 'Tercapai' : 'Belum'), 8, 'middle', '#64748b');
        }

        return $out.'</svg>';
    }

    /**
     * @param  list<array{label: string, value: float|int, capacity?: float}>  $data
     */
    private static function hbars(array $data, bool $percent): string
    {
        $h = max(60, 16 + count($data) * 24);
        $out = self::open($h);
        if ($data === []) {
            return $out.self::text(self::W / 2, $h / 2, 'Belum ada data', 11, 'middle', '#64748b').'</svg>';
        }
        $max = $percent ? 100 : max(1, max(array_map(fn (array $d): float => (float) $d['value'], $data)));
        $left = 190;
        $barW = self::W - $left - 90;
        foreach ($data as $i => $d) {
            $y = 10 + $i * 24;
            $value = (float) $d['value'];
            $out .= self::text($left - 8, $y + 11, mb_strimwidth($d['label'], 0, 34, '…'), 9, 'end');
            $out .= self::rect($left, $y, $barW, 14, '#e2e8f0');
            $color = $percent ? ($value < 30 ? '#ef4444' : ($value < 60 ? '#f59e0b' : '#10b981')) : self::COLORS[0];
            $out .= self::rect($left, $y, $barW * min(1, $value / $max), 14, $color);
            $suffix = $percent ? '%'.(isset($d['capacity']) ? ' · '.self::short((float) $d['capacity']).' kL' : '') : '';
            $out .= self::text($left + $barW + 6, $y + 11, self::short($value).$suffix, 9, 'start', '#0f172a', true);
        }

        return $out.'</svg>';
    }

    /**
     * @param  list<array{day: int, plan: int|float, real: int|float|null}>  $points
     */
    private static function scurve(array $points): string
    {
        return self::lines([
            'labels' => array_map(fn (array $p): string => (string) $p['day'], $points),
            'series' => [
                ['name' => 'Rencana kumulatif', 'values' => array_map(fn (array $p): float => (float) $p['plan'], $points)],
                ['name' => 'Realisasi kumulatif', 'values' => array_map(fn (array $p): float => (float) ($p['real'] ?? 0), array_values(array_filter($points, fn (array $p): bool => $p['real'] !== null)))],
            ],
        ]);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{label: string, values: list<int|null>}>  $rows
     */
    private static function heatmap(array $columns, array $rows): string
    {
        $h = max(60, 30 + count($rows) * 26);
        $out = self::open($h);
        if ($rows === []) {
            return $out.self::text(self::W / 2, $h / 2, 'Belum ada data', 11, 'middle', '#64748b').'</svg>';
        }
        $left = 190;
        $cellW = (self::W - $left - 10) / max(1, count($columns));
        foreach ($columns as $c => $column) {
            $out .= self::text($left + $cellW * $c + $cellW / 2, 14, $column, 9, 'middle', '#64748b', true);
        }
        foreach ($rows as $r => $row) {
            $y = 22 + $r * 26;
            $out .= self::text($left - 8, $y + 15, mb_strimwidth($row['label'], 0, 34, '…'), 9, 'end');
            foreach ($row['values'] as $c => $v) {
                $fill = match (true) {
                    $v === null => '#e2e8f0',
                    $v >= 90 => '#10b981',
                    $v >= 80 => '#6ee7b7',
                    $v >= 70 => '#fcd34d',
                    default => '#fb7185',
                };
                $out .= self::rect($left + $cellW * $c + 2, $y, $cellW - 4, 22, $fill);
                $out .= self::text($left + $cellW * $c + $cellW / 2, $y + 15, $v === null ? '—' : $v.'%', 9, 'middle', '#0f172a', true);
            }
        }

        return $out.'</svg>';
    }

    private static function empty(): string
    {
        return self::open(60).self::text(self::W / 2, 30, 'Belum ada data', 11, 'middle', '#64748b').'</svg>';
    }

    private static function open(int|float $h): string
    {
        $h = (int) ceil($h);

        return "<svg xmlns='http://www.w3.org/2000/svg' width='".self::W."' height='{$h}' viewBox='0 0 ".self::W." {$h}'><rect x='0' y='0' width='".self::W."' height='{$h}' fill='#ffffff'/>";
    }

    private static function grid(float $left, float $top, float $plotW, float $plotH, float $max): string
    {
        $out = '';
        for ($k = 0; $k <= 4; $k++) {
            $y = round($top + $plotH - $plotH * $k / 4, 1);
            $out .= "<line x1='{$left}' y1='{$y}' x2='".($left + $plotW)."' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
            $out .= self::text($left - 6, $y + 3, self::short($max * $k / 4), 8, 'end', '#64748b');
        }

        return $out."<line x1='{$left}' y1='".($top + $plotH)."' x2='".($left + $plotW)."' y2='".($top + $plotH)."' stroke='#94a3b8' stroke-width='1'/>";
    }

    /**
     * @param  list<string>  $names
     */
    private static function legend(array $names, float $y): string
    {
        $out = '';
        $x = 52;
        foreach ($names as $i => $name) {
            $out .= self::rect($x, $y - 8, 10, 10, self::COLORS[$i % 8]);
            $out .= self::text($x + 14, $y, $name, 9, 'start');
            $x += 24 + mb_strlen($name) * 5.6;
        }

        return $out;
    }

    private static function rect(float $x, float $y, float $w, float $h, string $fill): string
    {
        if ($w <= 0 || $h <= 0) {
            return '';
        }

        return "<rect x='".round($x, 1)."' y='".round($y, 1)."' width='".round($w, 1)."' height='".round($h, 1)."' fill='{$fill}'/>";
    }

    private static function text(float $x, float $y, string $text, int $size, string $anchor, string $fill = '#334155', bool $bold = false): string
    {
        $weight = $bold ? " font-weight='bold'" : '';

        return "<text x='".round($x, 1)."' y='".round($y, 1)."' font-size='{$size}' text-anchor='{$anchor}' fill='{$fill}' ".self::FONT."{$weight}>".htmlspecialchars($text, ENT_XML1 | ENT_QUOTES).'</text>';
    }

    /** A ring segment between two angles (radians, 0 = 3 o'clock, clockwise). */
    private static function arcPath(float $cx, float $cy, float $r, float $inner, float $from, float $to): string
    {
        $large = $to - $from > M_PI ? 1 : 0;
        $p = fn (float $radius, float $a): string => round($cx + $radius * cos($a), 2).' '.round($cy + $radius * sin($a), 2);

        return 'M '.$p($r, $from).' A '.$r.' '.$r.' 0 '.$large.' 1 '.$p($r, $to).' L '.$p($inner, $to).' A '.$inner.' '.$inner.' 0 '.$large.' 0 '.$p($inner, $from).' Z';
    }

    /** A round axis maximum just above the value. */
    private static function nice(float $value): float
    {
        if ($value <= 0) {
            return 1.0;
        }
        $magnitude = 10 ** floor(log10($value));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($value <= $step * $magnitude) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }

    private static function short(float $value): string
    {
        $abs = abs($value);

        return match (true) {
            $abs >= 1_000_000_000 => number_format($value / 1_000_000_000, 1, ',', '.').' M',
            $abs >= 1_000_000 => number_format($value / 1_000_000, 1, ',', '.').' jt',
            $abs >= 10_000 => number_format($value / 1000, 1, ',', '.').' rb',
            $abs >= 100 || floor($value) === $value => number_format($value, 0, ',', '.'),
            default => number_format($value, 2, ',', '.'),
        };
    }
}
