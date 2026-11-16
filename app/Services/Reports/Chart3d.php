<?php

namespace App\Services\Reports;

use GdImage;

/**
 * Server-side 3D charts (PNG data URIs, drawn with GD) for the PDF, Excel and
 * web reports: dompdf, ExcelJS and the rich-text editor all show a plain <img>,
 * so every chart prints and exports exactly as previewed. Labels use the DejaVu
 * fonts dompdf ships.
 */
class Chart3d
{
    /** @var list<array{0: int, 1: int, 2: int}> */
    private const PALETTE = [
        [31, 119, 180], [255, 127, 14], [44, 160, 44], [214, 39, 40], [148, 103, 189],
        [140, 86, 75], [227, 119, 194], [127, 127, 127], [188, 189, 34], [23, 190, 207],
    ];

    /**
     * A 3D pie with a legend card (label, value, share and total) on the right.
     *
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     */
    public function pie(string $title, array $labels, array $values, int $width = 1000, int $height = 440): string
    {
        $image = $this->canvas($width, $height);
        $total = array_sum(array_map('floatval', $values));

        if ($title !== '') {
            $this->text($image, $title, 13, $width / 2, 26, [11, 37, 69], true, 'center');
        } else {
            $topIdx = $total > 0 ? (int) array_keys($values, max($values))[0] : null;
            $dominant = $topIdx !== null && isset($labels[$topIdx])
                ? 'Dominan: '.$labels[$topIdx].' ('.$this->short((float) $values[$topIdx]).' · '.round((float) $values[$topIdx] / $total * 100).'%)'
                : 'Status: Belum ada realisasi pada periode ini';
            $this->summaryStrip($image, $width, [
                'Total: '.$this->short((float) $total),
                'Kategori: '.count($labels),
                $dominant,
            ]);
        }

        $cx = 250;
        $cy = 235;
        $w = 370;
        $h = 196;
        $depth = 28;

        if ($total <= 0) {
            // Draw a 3D neutral slate cylinder so the chart never looks blank when values are 0.
            $side = imagecolorallocate($image, 148, 163, 184);
            $topFace = imagecolorallocate($image, 226, 232, 240);
            $badge = imagecolorallocate($image, 255, 255, 255);
            for ($z = $depth; $z > 0; $z--) {
                imagefilledarc($image, $cx, $cy + $z, $w, $h, 0, 360, $side, IMG_ARC_PIE);
            }
            imagefilledarc($image, $cx, $cy, $w, $h, 0, 360, $topFace, IMG_ARC_PIE);
            imagefilledellipse($image, $cx, $cy, 195, 100, $badge);
            $this->text($image, '0', 16, $cx, $cy - 2, [51, 65, 85], true, 'center');
            $this->text($image, 'Belum ada realisasi', 9, $cx, $cy + 16, [100, 116, 139], false, 'center');
        } else {
            $slices = $this->slices($values, (float) $total);

            // Extruded side: darker slices stacked from the bottom up.
            for ($z = $depth; $z > 0; $z--) {
                foreach ($slices as [$from, $to, $i]) {
                    imagefilledarc($image, $cx, $cy + $z, $w, $h, (int) round($from), (int) round($to), $this->color($image, $i, 0.65), IMG_ARC_PIE);
                }
            }
            foreach ($slices as [$from, $to, $i]) {
                imagefilledarc($image, $cx, $cy, $w, $h, (int) round($from), (int) round($to), $this->color($image, $i), IMG_ARC_PIE);
            }

            // Percentages on the top face.
            foreach ($slices as [$from, $to, $i]) {
                $share = (float) $values[$i] / $total;
                if ($share < 0.04) {
                    continue;
                }
                $mid = deg2rad(($from + $to) / 2);
                $this->text($image, round($share * 100).'%', 10, $cx + cos($mid) * $w * 0.32, $cy + sin($mid) * $h * 0.32 + 4, [255, 255, 255], true, 'center');
            }
        }

        $this->legendCard(
            $image,
            $labels,
            array_map(fn ($v): string => $this->short((float) $v).' ('.($total > 0 ? round((float) $v / $total * 100) : 0).'%)', $values),
            $this->short((float) $total),
            480,
            56,
            $width - 22,
            $height - 20,
        );

        return $this->dataUri($image);
    }

    /**
     * 3D clustered bars (one bar per series for each category) with value labels.
     *
     * @param  list<string>  $categories
     * @param  array<string, list<int|float>>  $series  name => values per category
     */
    public function bars(string $title, array $categories, array $series, int $width = 1100, int $height = 460): string
    {
        $image = $this->canvas($width, $height);
        $this->text($image, $title, 14, $width / 2, 26, [11, 37, 69], true, 'center');

        $left = 60;
        $right = $width - 30;
        $top = 70;
        $bottom = $height - 110;
        $depth = 10;
        $max = max(1, ...array_merge(...array_values(array_map(fn (array $v): array => array_map('floatval', $v), $series))));
        $max = $this->niceMax($max);

        // Axis & grid.
        $gridColor = imagecolorallocate($image, 226, 232, 240);
        $axisColor = imagecolorallocate($image, 100, 116, 139);
        for ($g = 0; $g <= 5; $g++) {
            $y = (int) round($bottom - ($bottom - $top) * $g / 5);
            imageline($image, $left, $y, $right, $y, $gridColor);
            $this->text($image, (string) round($max * $g / 5), 9, $left - 8, $y + 4, [71, 85, 105], false, 'right');
        }
        imageline($image, $left, $top, $left, $bottom, $axisColor);
        imageline($image, $left, $bottom, $right, $bottom, $axisColor);

        $count = max(1, count($categories));
        $groupWidth = ($right - $left) / $count;
        $seriesCount = max(1, count($series));
        $barWidth = min(38, ($groupWidth * 0.7) / $seriesCount);

        foreach ($categories as $c => $category) {
            $groupLeft = $left + $groupWidth * $c + ($groupWidth - $barWidth * $seriesCount) / 2;
            $s = 0;
            foreach ($series as $values) {
                $value = (float) ($values[$c] ?? 0);
                $x1 = (int) round($groupLeft + $barWidth * $s);
                $x2 = (int) round($x1 + $barWidth - 4);
                $y = (int) round($bottom - ($bottom - $top) * $value / $max);

                if ($value > 0) {
                    imagefilledpolygon($image, [$x2, $y, $x2 + $depth, $y - $depth, $x2 + $depth, $bottom - $depth, $x2, $bottom], $this->color($image, $s, 0.6));
                    imagefilledpolygon($image, [$x1, $y, $x1 + $depth, $y - $depth, $x2 + $depth, $y - $depth, $x2, $y], $this->color($image, $s, 1.25));
                    imagefilledrectangle($image, $x1, $y, $x2, $bottom, $this->color($image, $s));
                }
                $this->text($image, $this->format($value), 8, ($x1 + $x2) / 2 + $depth / 2, $y - $depth - 4, [30, 41, 59], true, 'center');
                $s++;
            }

            $this->wrapped($image, $category, 8, $left + $groupWidth * $c + $groupWidth / 2, $bottom + 16, (int) $groupWidth - 6);
        }

        $this->legend($image, array_keys($series), array_fill(0, count($series), ''), $left, $height - 34, horizontal: true, maxWidth: $width - 40);

        return $this->dataUri($image);
    }

    /**
     * 3D columns for the Resume Bulanan: grouped or stacked, optionally with the
     * last series as a 3D line on a right-hand axis (e.g. beban over kWh), and
     * short axis labels (rb / jt / M) for large figures.
     *
     * @param  list<string>  $categories
     * @param  array<string, list<int|float>>  $series  name => values per category
     */
    public function columns(string $title, array $categories, array $series, bool $stacked = false, bool $lineLast = false, int $width = 1000, int $height = 440): string
    {
        $image = $this->canvas($width, $height);
        if ($title !== '') {
            $this->text($image, $title, 13, $width / 2, 24, [11, 37, 69], true, 'center');
        } else {
            $this->seriesSummaryStrip($image, $width, $series, isLine: false);
        }

        $names = array_keys($series);
        $line = [];
        if ($lineLast && count($series) > 1) {
            $lineName = array_pop($names);
            $line = [$lineName => $series[$lineName]];
        }
        $bars = array_map(fn (string $n): array => array_map('floatval', $series[$n]), array_combine($names, $names) ?: []);

        [$left, $right, $top, $bottom, $depth] = [74, $width - ($line !== [] ? 70 : 30), 62, $height - 92, 12];
        $count = max(1, count($categories));
        $rawMax = 0.0;
        foreach (array_keys($categories) as $c) {
            $values = array_map(fn (array $v): float => max(0, $v[$c] ?? 0), $bars);
            $rawMax = max($rawMax, $stacked ? array_sum($values) : ($values === [] ? 0 : max($values)));
        }
        $lineRawMax = $line !== [] ? max(0.0, ...array_map('floatval', reset($line))) : 0.0;
        $max = $this->niceMax(max(1.0, $rawMax));
        $this->grid($image, $left, $right, $top, $bottom, $max, $depth);

        $groupWidth = ($right - $left) / $count;
        $seriesCount = $stacked ? 1 : max(1, count($bars));
        $barWidth = max(4, min(46, ($groupWidth * 0.68) / $seriesCount));
        $labelEvery = (int) ceil($count / 16);
        $showValues = $count <= 12;

        foreach ($categories as $c => $category) {
            $groupLeft = $left + $groupWidth * $c + ($groupWidth - $barWidth * $seriesCount) / 2;
            $base = $bottom;
            $s = 0;
            foreach ($bars as $values) {
                $value = max(0, $values[$c] ?? 0);
                $x1 = (int) round($groupLeft + ($stacked ? 0 : $barWidth * $s));
                $x2 = (int) round($x1 + $barWidth - ($stacked ? 2 : 3));
                $h = ($bottom - $top) * $value / $max;
                $y = (int) round($base - $h);
                if ($value > 0) {
                    $this->box($image, $x1, $y, $x2, (int) round($base), $depth, $s, ! $stacked || $s === count($bars) - 1);
                    if ($showValues && ! $stacked) {
                        $this->text($image, $this->short($value), 8, ($x1 + $x2) / 2 + $depth / 2, $y - $depth - 3, [30, 41, 59], true, 'center');
                    }
                }
                if ($stacked) {
                    $base -= $h;
                }
                $s++;
            }
            if ($stacked && $showValues) {
                $total = array_sum(array_map(fn (array $v): float => max(0, $v[$c] ?? 0), $bars));
                if ($total > 0) {
                    $this->text($image, $this->short($total), 8, $groupLeft + $barWidth / 2 + $depth / 2, $base - $depth - 3, [30, 41, 59], true, 'center');
                }
            }
            if ($c % $labelEvery === 0) {
                $this->wrapped($image, (string) $category, 8, $left + $groupWidth * $c + $groupWidth / 2, $bottom + 16, max(30, (int) $groupWidth * $labelEvery - 4));
            }
        }

        if ($line !== []) {
            $values = array_map('floatval', reset($line));
            $lmax = $this->niceMax(max(1.0, ...$values));
            $points = [];
            foreach (array_keys($categories) as $c) {
                $points[] = [$left + $groupWidth * $c + $groupWidth / 2, $bottom - ($bottom - $top) * ($values[$c] ?? 0) / $lmax];
            }
            $this->ribbon($image, $points, count($bars), 6);
            for ($g = 0; $g <= 5; $g++) {
                $this->text($image, $this->short($lmax * $g / 5), 8, $right + 8, (int) round($bottom - ($bottom - $top) * $g / 5) + 4, [71, 85, 105]);
            }
        }

        if ($rawMax <= 0.0 && $lineRawMax <= 0.0) {
            $this->emptyWallBadge($image, (int) (($left + $right) / 2), (int) (($top + $bottom) / 2));
        }

        $this->legend($image, array_keys($series), array_fill(0, count($series), ''), $left, $height - 26, horizontal: true, maxWidth: $width - 40);

        return $this->dataUri($image);
    }

    /**
     * 3D lines (each series a shaded ribbon with depth), optionally with a
     * dashed threshold (e.g. the vibration alarm limit).
     *
     * @param  list<string>  $categories
     * @param  array<string, list<int|float|null>>  $series
     */
    public function lines(string $title, array $categories, array $series, ?float $threshold = null, ?string $thresholdLabel = null, int $width = 1000, int $height = 440): string
    {
        $image = $this->canvas($width, $height);
        if ($title !== '') {
            $this->text($image, $title, 13, $width / 2, 24, [11, 37, 69], true, 'center');
        } else {
            $this->seriesSummaryStrip($image, $width, $series, isLine: true);
        }

        [$left, $right, $top, $bottom] = [74, $width - 30, 62, $height - 92];
        $all = [1.0, (float) $threshold];
        $rawDataMax = 0.0;
        foreach ($series as $values) {
            $clean = array_map('floatval', array_filter($values, fn ($v): bool => $v !== null));
            if ($clean !== []) {
                $rawDataMax = max($rawDataMax, max($clean));
            }
            $all = [...$all, ...$clean];
        }
        $max = $this->niceMax(max($all));
        $this->grid($image, $left, $right, $top, $bottom, $max, 8);
        $count = max(1, count($categories));
        $x = fn (int $i): float => $count === 1 ? ($left + $right) / 2 : $left + ($right - $left - 10) * $i / ($count - 1);
        $y = fn (float $v): float => $bottom - ($bottom - $top) * $v / $max;

        if ($threshold !== null) {
            $ty = (int) round($y($threshold));
            $red = imagecolorallocate($image, 220, 38, 38);
            imagesetthickness($image, 2);
            for ($dx = $left; $dx < $right; $dx += 12) {
                imageline($image, $dx, $ty, min($right, $dx + 7), $ty, $red);
            }
            imagesetthickness($image, 1);
            $this->text($image, ($thresholdLabel ?? 'Batas').' '.$this->short($threshold), 8, $left + 18, $ty - 6, [220, 38, 38], true);
        }

        $s = 0;
        foreach ($series as $values) {
            $points = [];
            $indexedValues = [];
            foreach (array_keys($categories) as $i) {
                if (($values[$i] ?? null) !== null) {
                    $val = (float) $values[$i];
                    $pt = [$x($i), $y($val)];
                    $points[] = $pt;
                    $indexedValues[] = [$pt[0], $pt[1], $val];
                }
            }
            $this->ribbon($image, $points, $s, 7);

            // Show value labels on points when count <= 10, or on peak + last non-zero point when count > 10.
            if ($indexedValues !== []) {
                $maxVal = max(array_column($indexedValues, 2));
                if ($maxVal > 0) {
                    if ($count <= 10) {
                        foreach ($indexedValues as [$px, $py, $val]) {
                            if ($val > 0) {
                                $this->text($image, $this->short($val), 7.5, $px + 3, $py - 10, [30, 41, 59], true, 'center');
                            }
                        }
                    } else {
                        $peakShown = false;
                        $lastIdx = count($indexedValues) - 1;
                        foreach ($indexedValues as $idx => [$px, $py, $val]) {
                            if ((! $peakShown && $val === $maxVal) || ($idx === $lastIdx && $val > 0)) {
                                $this->text($image, $this->short($val), 7.5, $px + 3, $py - 10, [30, 41, 59], true, 'center');
                                if ($val === $maxVal) {
                                    $peakShown = true;
                                }
                            }
                        }
                    }
                }
            }
            $s++;
        }

        if ($rawDataMax <= 0.0) {
            $this->emptyWallBadge($image, (int) (($left + $right) / 2), (int) (($top + $bottom) / 2));
        }

        $labelEvery = (int) ceil($count / 16);
        foreach ($categories as $i => $category) {
            if ($i % $labelEvery === 0) {
                $this->text($image, (string) $category, 8, $x($i), $bottom + 18, [51, 65, 85], false, 'center');
            }
        }
        $this->legend($image, array_keys($series), array_fill(0, count($series), ''), $left, $height - 26, horizontal: true, maxWidth: $width - 40);

        return $this->dataUri($image);
    }

    /**
     * A 3D donut (ring with depth) with a legend card of value, share and total.
     *
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     */
    public function donut(string $title, array $labels, array $values, int $width = 1000, int $height = 440): string
    {
        $image = $this->canvas($width, $height);
        $total = array_sum(array_map('floatval', $values));

        if ($title !== '') {
            $this->text($image, $title, 13, $width / 2, 24, [11, 37, 69], true, 'center');
        } else {
            $topIdx = $total > 0 ? (int) array_keys($values, max($values))[0] : null;
            $dominant = $topIdx !== null && isset($labels[$topIdx])
                ? 'Dominan: '.$labels[$topIdx].' ('.$this->short((float) $values[$topIdx]).' · '.round((float) $values[$topIdx] / $total * 100).'%)'
                : 'Status: Belum ada data pada periode ini';
            $this->summaryStrip($image, $width, [
                'Total: '.$this->short((float) $total),
                'Kategori: '.count($labels),
                $dominant,
            ]);
        }

        [$cx, $cy, $w, $h, $depth] = [250, 235, 360, 192, 26];

        if ($total <= 0) {
            // Draw a 3D neutral slate donut ring so the chart is never blank when values are 0.
            $side = imagecolorallocate($image, 148, 163, 184);
            $topFace = imagecolorallocate($image, 226, 232, 240);
            for ($z = $depth; $z > 0; $z--) {
                imagefilledarc($image, $cx, $cy + $z, $w, $h, 0, 360, $side, IMG_ARC_PIE);
            }
            imagefilledarc($image, $cx, $cy, $w, $h, 0, 360, $topFace, IMG_ARC_PIE);
            imagefilledellipse($image, $cx, $cy + (int) ($depth * 0.55), (int) ($w * 0.5), (int) ($h * 0.5), imagecolorallocate($image, 203, 213, 225));
            imagefilledellipse($image, $cx, $cy, (int) ($w * 0.5), (int) ($h * 0.5), imagecolorallocate($image, 255, 255, 255));
            $this->text($image, '0', 15, $cx, $cy + 2, [51, 65, 85], true, 'center');
            $this->text($image, 'Belum ada data', 8.5, $cx, $cy + 18, [100, 116, 139], false, 'center');
        } else {
            $slices = $this->slices($values, $total);
            for ($z = $depth; $z > 0; $z--) {
                foreach ($slices as [$from, $to, $i]) {
                    imagefilledarc($image, $cx, $cy + $z, $w, $h, (int) round($from), (int) round($to), $this->color($image, $i, 0.62), IMG_ARC_PIE);
                }
            }
            foreach ($slices as [$from, $to, $i]) {
                imagefilledarc($image, $cx, $cy, $w, $h, (int) round($from), (int) round($to), $this->color($image, $i), IMG_ARC_PIE);
            }
            // The hole: its inner wall (dark) then the top opening.
            imagefilledellipse($image, $cx, $cy + (int) ($depth * 0.55), (int) ($w * 0.5), (int) ($h * 0.5), imagecolorallocate($image, 203, 213, 225));
            imagefilledellipse($image, $cx, $cy, (int) ($w * 0.5), (int) ($h * 0.5), imagecolorallocate($image, 255, 255, 255));
            $this->text($image, $this->short($total), 15, $cx, $cy + 2, [11, 37, 69], true, 'center');
            $this->text($image, 'Total', 9, $cx, $cy + 18, [100, 116, 139], false, 'center');
            foreach ($slices as [$from, $to, $i]) {
                $share = (float) $values[$i] / $total;
                if ($share >= 0.05) {
                    $mid = deg2rad(($from + $to) / 2);
                    $this->text($image, round($share * 100).'%', 9, $cx + cos($mid) * $w * 0.375, $cy + sin($mid) * $h * 0.375 + 4, [255, 255, 255], true, 'center');
                }
            }
        }

        $this->legendCard(
            $image,
            $labels,
            array_map(fn ($v): string => $this->short((float) $v).' ('.($total > 0 ? round((float) $v / $total * 100) : 0).'%)', $values),
            $this->short((float) $total),
            480,
            56,
            $width - 22,
            $height - 20,
        );

        return $this->dataUri($image);
    }

    /**
     * 3D half-ring gauges against a target, side by side, with summary cards below.
     *
     * @param  list<array{label: string, value: float, target: float, good: string, no_data?: bool}>  $items
     */
    public function gauges(string $title, array $items, int $width = 1000, int $height = 440): string
    {
        $image = $this->canvas($width, $height);
        if ($title !== '') {
            $this->text($image, $title, 13, $width / 2, 24, [11, 37, 69], true, 'center');
        } else {
            $parts = array_map(
                fn (array $g): string => $g['label'].': '.number_format((float) $g['value'], 1, ',', '.').'% (Target '.($g['good'] === 'up' ? '≥ ' : '≤ ').$this->short((float) $g['target']).'%)',
                $items,
            );
            $this->summaryStrip($image, $width, $parts);
        }

        $slot = $width / max(1, count($items));
        foreach ($items as $i => $g) {
            $cx = (int) round($slot * $i + $slot / 2);
            $cy = 235;
            [$w, $h, $depth] = [260, 200, 16];
            $value = max(0.0, min(100.0, (float) $g['value']));
            $noData = (bool) ($g['no_data'] ?? false);
            $ok = ! $noData && ($g['good'] === 'up' ? $g['value'] >= $g['target'] : $g['value'] <= $g['target']);
            $fill = $noData ? 7 : ($ok ? 2 : 1);
            $end = (int) round(180 + 180 * $value / 100);

            // Extruded body going back-right (track & value, darker), then the face, then the hole.
            for ($z = $depth; $z > 0; $z--) {
                $ox = (int) round($z * 0.7);
                imagefilledarc($image, $cx + $ox, $cy + $z, $w, $h, 180, 360, imagecolorallocate($image, 148, 163, 184), IMG_ARC_PIE);
                if ($value > 0) {
                    imagefilledarc($image, $cx + $ox, $cy + $z, $w, $h, 180, $end, $this->color($image, $fill, 0.6), IMG_ARC_PIE);
                }
                imagefilledarc($image, $cx + $ox, $cy + $z, (int) ($w * 0.58), (int) ($h * 0.58), 180, 360, imagecolorallocate($image, 100, 116, 139), IMG_ARC_PIE);
            }
            imagefilledarc($image, $cx, $cy, $w, $h, 180, 360, imagecolorallocate($image, 226, 232, 240), IMG_ARC_PIE);
            if ($value > 0) {
                imagefilledarc($image, $cx, $cy, $w, $h, 180, $end, $this->color($image, $fill), IMG_ARC_PIE);
            }
            imagefilledarc($image, $cx, $cy, (int) ($w * 0.58), (int) ($h * 0.58), 180, 360, imagecolorallocate($image, 255, 255, 255), IMG_ARC_PIE);

            // Target marker line on the gauge ring.
            $ta = deg2rad(180 + 180 * min(100, (float) $g['target']) / 100);
            imagesetthickness($image, 3);
            imageline($image, (int) ($cx + cos($ta) * $w * 0.27), (int) ($cy + sin($ta) * $h * 0.27), (int) ($cx + cos($ta) * $w * 0.53), (int) ($cy + sin($ta) * $h * 0.53), imagecolorallocate($image, 15, 23, 42));
            imagesetthickness($image, 1);

            // 0% and 100% scale labels at the ends of the half-ring.
            $this->text($image, '0%', 8, $cx - $w * 0.39, $cy + 28, [100, 116, 139], false, 'center');
            $this->text($image, '100%', 8, $cx + $w * 0.42, $cy + 28, [100, 116, 139], false, 'center');

            $this->text($image, number_format((float) $g['value'], 1, ',', '.').'%', 17, $cx, $cy - 10, [11, 37, 69], true, 'center');
            $this->text($image, $g['label'], 12, $cx, $cy + 36, [11, 37, 69], true, 'center');

            // Summary card box below the gauge.
            $cardLeft = $cx - 128;
            $cardRight = $cx + 128;
            imagefilledrectangle($image, $cardLeft, 305, $cardRight, 395, imagecolorallocate($image, 248, 250, 252));
            imagerectangle($image, $cardLeft, 305, $cardRight, 395, imagecolorallocate($image, 203, 213, 225));
            $targetText = 'Target '.($g['good'] === 'up' ? '≥ ' : '≤ ').$this->short((float) $g['target']).'%';
            $this->text($image, $targetText, 9, $cx, 330, [51, 65, 85], true, 'center');
            $diff = round((float) $g['value'] - (float) $g['target'], 1);
            $diffLabel = 'Selisih thd target: '.($diff >= 0 ? '+' : '').number_format($diff, 1, ',', '.').'%';
            $this->text($image, $diffLabel, 8.5, $cx, 352, [100, 116, 139], false, 'center');

            $statusText = $noData ? 'Belum ada jam operasi' : ($ok ? 'Target Tercapai' : 'Belum Tercapai');
            $statusRgb = $noData ? [100, 116, 139] : ($ok ? [21, 128, 61] : [180, 83, 9]);
            $this->text($image, $statusText, 9.5, $cx, 378, $statusRgb, true, 'center');
        }

        return $this->dataUri($image);
    }

    /**
     * 3D horizontal bars (label left, value right); `$percent` draws 0–100 %
     * levels coloured by fill (e.g. tank levels).
     *
     * @param  list<array{label: string, value: float|int, capacity?: float}>  $rows
     */
    public function hbars(string $title, array $rows, bool $percent = false, int $width = 1000): string
    {
        $height = max(440, 96 + count($rows) * 36);
        $image = $this->canvas($width, $height);
        $values = array_map(fn (array $r): float => (float) $r['value'], $rows);
        $sum = array_sum($values);

        if ($title !== '') {
            $this->text($image, $title, 13, $width / 2, 24, [11, 37, 69], true, 'center');
        } else {
            $maxVal = $values === [] ? 0.0 : max($values);
            $avgVal = $values === [] ? 0.0 : $sum / count($values);
            $unitSuffix = $percent ? '%' : '';
            $this->summaryStrip($image, $width, [
                'Jumlah Item: '.count($rows),
                ($percent ? 'Rata-rata Level: ' : 'Total: ').$this->short($percent ? $avgVal : $sum).$unitSuffix,
                'Tertinggi: '.$this->short($maxVal).$unitSuffix,
            ]);
        }

        if ($rows === []) {
            $this->emptyWallBadge($image, (int) ($width / 2), (int) ($height / 2));

            return $this->dataUri($image);
        }

        $left = 290;
        $barMax = $width - $left - 150;
        $max = $percent ? 100.0 : max(1.0, ...$values);
        $rowCount = count($rows);
        $step = $rowCount <= 7 ? min(46, (int) floor(($height - 96) / max(1, $rowCount))) : 36;
        $startY = max(60, (int) round(52 + (($height - 76) - $rowCount * $step) / 2));

        foreach ($rows as $i => $row) {
            $y = $startY + $i * $step;
            $value = (float) $row['value'];
            $this->text($image, mb_strimwidth($row['label'], 0, 42, '…'), 9, $left - 12, $y + 15, [30, 41, 59], false, 'right');
            // Empty track, then the bar as a 3D box.
            $this->box($image, $left, $y + 3, $left + $barMax, $y + 21, 7, 7, true, track: true);
            $palette = $percent ? ($value < 30 ? 3 : ($value < 60 ? 1 : 2)) : $i;
            if ($value > 0) {
                $this->box($image, $left, $y + 3, (int) round($left + $barMax * min(1, $value / $max)), $y + 21, 7, $palette, true);
            }
            $suffix = $percent ? '%'.(isset($row['capacity']) ? ' · '.$this->short((float) $row['capacity']).' kL' : '') : '';
            $this->text($image, $this->short($value).$suffix, 9, $left + $barMax + 14, $y + 16, [11, 37, 69], true);
        }

        return $this->dataUri($image);
    }

    /**
     * Top summary strip inside a chart canvas when no explicit title is passed.
     *
     * @param  list<string>  $items
     */
    private function summaryStrip(GdImage $image, int $width, array $items): void
    {
        $bg = imagecolorallocate($image, 241, 245, 249);
        $border = imagecolorallocate($image, 203, 213, 225);
        imagefilledrectangle($image, 20, 10, $width - 20, 40, $bg);
        imagerectangle($image, 20, 10, $width - 20, 40, $border);

        $text = implode('    |    ', array_filter($items, fn (string $s): bool => $s !== ''));
        $this->text($image, mb_strimwidth($text, 0, 128, '…'), 8.5, $width / 2, 29, [30, 41, 59], true, 'center');
    }

    /**
     * Build a top summary strip from a chart's series map (Total / Akhir, Rata-rata, Maks).
     *
     * @param  array<string, list<int|float|null>>  $series
     */
    private function seriesSummaryStrip(GdImage $image, int $width, array $series, bool $isLine): void
    {
        $parts = [];
        foreach (array_slice($series, 0, 3, true) as $name => $rawValues) {
            $values = array_values(array_map('floatval', array_filter($rawValues, fn ($v): bool => $v !== null)));
            $sum = array_sum($values);
            $max = $values === [] ? 0.0 : max($values);
            $last = $values === [] ? 0.0 : (float) end($values);
            $shortName = mb_strimwidth((string) $name, 0, 22, '…');
            $parts[] = $isLine
                ? "{$shortName}: Akhir ".$this->short($last).' (Maks '.$this->short($max).')'
                : "{$shortName}: Total ".$this->short($sum).' (Maks '.$this->short($max).')';
        }
        $this->summaryStrip($image, $width, $parts);
    }

    /**
     * Centered pill badge drawn on the 3D chart wall when all values are zero.
     */
    private function emptyWallBadge(GdImage $image, int $cx, int $cy): void
    {
        $bg = imagecolorallocate($image, 248, 250, 252);
        $border = imagecolorallocate($image, 203, 213, 225);
        imagefilledrectangle($image, $cx - 190, $cy - 22, $cx + 190, $cy + 22, $bg);
        imagerectangle($image, $cx - 190, $cy - 22, $cx + 190, $cy + 22, $border);
        $this->text($image, 'Belum ada data realisasi pada periode ini (nilai 0)', 9, $cx, $cy + 4, [100, 116, 139], true, 'center');
    }

    /**
     * Right-side bordered legend card for Pie and Donut charts, with a Total footer.
     *
     * @param  list<string>  $labels
     * @param  list<string>  $values
     */
    private function legendCard(GdImage $image, array $labels, array $values, string $totalText, int $x1, int $y1, int $x2, int $y2): void
    {
        $bg = imagecolorallocate($image, 248, 250, 252);
        $border = imagecolorallocate($image, 226, 232, 240);
        $headerBg = imagecolorallocate($image, 241, 245, 249);
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $bg);
        imagefilledrectangle($image, $x1, $y1, $x2, $y1 + 28, $headerBg);
        imagerectangle($image, $x1, $y1, $x2, $y2, $border);
        imageline($image, $x1, $y1 + 28, $x2, $y1 + 28, $border);

        $this->text($image, 'RINCIAN KATEGORI', 8.5, $x1 + 14, $y1 + 19, [51, 65, 85], true);
        $this->text($image, 'NILAI (%)', 8.5, $x2 - 14, $y1 + 19, [51, 65, 85], true, 'right');

        $maxItems = min(10, count($labels));
        $availH = ($y2 - 34) - ($y1 + 36);
        $step = $maxItems > 0 ? min(26, max(18, (int) floor($availH / $maxItems))) : 24;
        $y = $y1 + 48;

        foreach (array_slice($labels, 0, $maxItems) as $i => $label) {
            imagefilledrectangle($image, $x1 + 14, $y - 10, $x1 + 26, $y + 2, $this->color($image, $i));
            $this->text($image, mb_strimwidth((string) $label, 0, 32, '…'), 9, $x1 + 34, $y, [30, 41, 59]);
            $this->text($image, (string) ($values[$i] ?? '0'), 9, $x2 - 14, $y, [15, 23, 42], true, 'right');
            $y += $step;
        }

        imageline($image, $x1, $y2 - 30, $x2, $y2 - 30, $border);
        $this->text($image, 'Total Keseluruhan', 9, $x1 + 14, $y2 - 11, [11, 37, 69], true);
        $this->text($image, $totalText, 9.5, $x2 - 14, $y2 - 11, [11, 37, 69], true, 'right');
    }

    /**
     * A 3D box (front, top and side faces) between two corners.
     */
    private function box(GdImage $image, int $x1, int $y, int $x2, int $base, int $depth, int $palette, bool $withTop, bool $track = false): void
    {
        $shade = fn (float $s): int => $this->color($image, $palette, $s);
        if ($track) {
            $front = imagecolorallocate($image, 241, 245, 249);
            $side = imagecolorallocate($image, 203, 213, 225);
            $top = imagecolorallocate($image, 226, 232, 240);
        } else {
            [$front, $side, $top] = [$shade(1.0), $shade(0.6), $shade(1.25)];
        }
        imagefilledpolygon($image, [$x2, $y, $x2 + $depth, $y - $depth, $x2 + $depth, $base - $depth, $x2, $base], $side);
        if ($withTop) {
            imagefilledpolygon($image, [$x1, $y, $x1 + $depth, $y - $depth, $x2 + $depth, $y - $depth, $x2, $y], $top);
        }
        imagefilledrectangle($image, $x1, $y, $x2, $base, $front);
    }

    /**
     * A line drawn as a 3D ribbon: a shaded band going back by `$depth`, the
     * line on its front edge and dots at each point.
     *
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function ribbon(GdImage $image, array $points, int $palette, int $depth): void
    {
        for ($i = 1; $i < count($points); $i++) {
            [$x1, $y1] = $points[$i - 1];
            [$x2, $y2] = $points[$i];
            imagefilledpolygon($image, [(int) $x1, (int) $y1, (int) ($x1 + $depth), (int) ($y1 - $depth), (int) ($x2 + $depth), (int) ($y2 - $depth), (int) $x2, (int) $y2], $this->color($image, $palette, 1.35));
        }
        imagesetthickness($image, 3);
        for ($i = 1; $i < count($points); $i++) {
            imageline($image, (int) $points[$i - 1][0], (int) $points[$i - 1][1], (int) $points[$i][0], (int) $points[$i][1], $this->color($image, $palette, 0.85));
        }
        imagesetthickness($image, 1);
        if (count($points) <= 31) {
            foreach ($points as [$x, $y]) {
                imagefilledellipse($image, (int) $x, (int) $y, 8, 8, $this->color($image, $palette, 0.7));
            }
        }
    }

    /**
     * Back wall, floor and grid of a 3D chart, with short value labels.
     */
    private function grid(GdImage $image, int $left, int $right, int $top, int $bottom, float $max, int $depth): void
    {
        $wall = imagecolorallocate($image, 248, 250, 252);
        $floor = imagecolorallocate($image, 226, 232, 240);
        $gridColor = imagecolorallocate($image, 226, 232, 240);
        $axisColor = imagecolorallocate($image, 100, 116, 139);
        imagefilledrectangle($image, $left + $depth, $top - $depth, $right + $depth, $bottom - $depth, $wall);
        imagefilledpolygon($image, [$left, $bottom, $left + $depth, $bottom - $depth, $right + $depth, $bottom - $depth, $right, $bottom], $floor);
        for ($g = 0; $g <= 5; $g++) {
            $y = (int) round($bottom - ($bottom - $top) * $g / 5);
            imageline($image, $left, $y, $left + $depth, $y - $depth, $gridColor);
            imageline($image, $left + $depth, $y - $depth, $right + $depth, $y - $depth, $gridColor);
            $this->text($image, $this->short($max * $g / 5), 9, $left - 8, $y + 4, [71, 85, 105], false, 'right');
        }
        imageline($image, $left, $top, $left, $bottom, $axisColor);
        imageline($image, $left, $bottom, $right, $bottom, $axisColor);
    }

    /**
     * @param  list<int|float>  $values
     * @return list<array{0: float, 1: float, 2: int}>
     */
    private function slices(array $values, float $total): array
    {
        $slices = [];
        $start = -90.0;
        foreach ($values as $i => $value) {
            $sweep = (float) $value / $total * 360;
            if ($sweep > 0) {
                $slices[] = [$start, $start + $sweep, $i];
            }
            $start += $sweep;
        }

        return $slices;
    }

    /** Large figures shortened for axes and labels: 1,2 rb · 3,4 jt · 1,5 M. */
    private function short(float $value): string
    {
        $abs = abs($value);

        return match (true) {
            $abs >= 1_000_000_000 => number_format($value / 1_000_000_000, 1, ',', '.').' M',
            $abs >= 1_000_000 => number_format($value / 1_000_000, 1, ',', '.').' jt',
            $abs >= 10_000 => number_format($value / 1000, 1, ',', '.').' rb',
            floor($value) === $value => number_format($value, 0, ',', '.'),
            default => number_format($value, $abs >= 100 ? 0 : 2, ',', '.'),
        };
    }

    private function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageantialias($image, true);
        imagerectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 226, 232, 240));

        return $image;
    }

    private function color(GdImage $image, int $index, float $shade = 1.0): int
    {
        [$r, $g, $b] = self::PALETTE[$index % count(self::PALETTE)];
        $apply = fn (int $c): int => (int) max(0, min(255, $shade <= 1 ? $c * $shade : $c + (255 - $c) * ($shade - 1)));

        return imagecolorallocate($image, $apply($r), $apply($g), $apply($b));
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $values
     */
    private function legend(GdImage $image, array $labels, array $values, int $x, int $y, bool $horizontal = false, int $maxWidth = 940): void
    {
        $startX = $x;
        foreach ($labels as $i => $label) {
            $text = $label.(($values[$i] ?? '') !== '' ? ' — '.$values[$i] : '');
            $itemWidth = 32 + (int) (mb_strlen($text) * 6.8);
            if ($horizontal && $x > $startX && ($x + $itemWidth) > $maxWidth) {
                $x = $startX;
                $y += 18;
            }
            imagefilledrectangle($image, $x, $y - 10, $x + 12, $y + 2, $this->color($image, $i));
            $this->text($image, $text, 8.5, $x + 18, $y, [30, 41, 59]);
            if ($horizontal) {
                $x += $itemWidth;
            } else {
                $y += 24;
            }
        }
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function text(GdImage $image, string $text, float $size, float $x, float $y, array $rgb, bool $bold = false, string $align = 'left'): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/'.($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));
        $box = imagettfbbox($size, 0, $font, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $textWidth = $box[2] - $box[0];
        $x = match ($align) {
            'center' => $x - $textWidth / 2,
            'right' => $x - $textWidth,
            default => $x,
        };

        imagettftext($image, $size, 0, (int) round($x), (int) round($y), imagecolorallocate($image, ...$rgb), $font, $text);
    }

    /** A category label wrapped onto up to three centred lines. */
    private function wrapped(GdImage $image, string $text, float $size, float $centerX, float $y, int $maxWidth): void
    {
        $lines = explode("\n", wordwrap($text, max(8, (int) ($maxWidth / 6)), "\n", true));
        foreach (array_slice($lines, 0, 3) as $i => $line) {
            $this->text($image, $line, $size, $centerX, $y + $i * 12, [51, 65, 85], false, 'center');
        }
    }

    private function niceMax(float $max): float
    {
        $magnitude = 10 ** floor(log10($max));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($max <= $step * $magnitude) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }

    private function format(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : number_format($value, 1, ',', '.');
    }

    private function dataUri(GdImage $image): string
    {
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
