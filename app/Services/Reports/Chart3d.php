<?php

namespace App\Services\Reports;

use GdImage;

/**
 * Small server-side 3D charts (PNG data URIs, drawn with GD) for the PDF
 * reports: dompdf and the rich-text editor both show a plain <img>, so the
 * chart prints exactly as previewed. Labels use the DejaVu fonts dompdf ships.
 */
class Chart3d
{
    /** @var list<array{0: int, 1: int, 2: int}> */
    private const PALETTE = [
        [31, 119, 180], [255, 127, 14], [44, 160, 44], [214, 39, 40], [148, 103, 189],
        [140, 86, 75], [227, 119, 194], [127, 127, 127], [188, 189, 34], [23, 190, 207],
    ];

    /**
     * A 3D pie with a legend (label, value and share) on the right.
     *
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     */
    public function pie(string $title, array $labels, array $values, int $width = 900, int $height = 420): string
    {
        $image = $this->canvas($width, $height);
        $this->text($image, $title, 14, $width / 2, 26, [11, 37, 69], true, 'center');

        $total = array_sum($values);
        $cx = 230;
        $cy = 215;
        $w = 360;
        $h = 190;
        $depth = 26;

        if ($total <= 0) {
            $this->text($image, 'Belum ada realisasi pada periode ini', 11, $cx, $cy, [100, 116, 139], false, 'center');
        } else {
            $slices = [];
            $start = -90.0;
            foreach ($values as $i => $value) {
                $sweep = $value / $total * 360;
                if ($sweep > 0) {
                    $slices[] = [$start, $start + $sweep, $i];
                }
                $start += $sweep;
            }

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
                $share = $values[$i] / $total;
                if ($share < 0.04) {
                    continue;
                }
                $mid = deg2rad(($from + $to) / 2);
                $this->text($image, round($share * 100).'%', 10, $cx + cos($mid) * $w * 0.32, $cy + sin($mid) * $h * 0.32 + 4, [255, 255, 255], true, 'center');
            }
        }

        $this->legend($image, $labels, array_map(fn ($v): string => (string) $v.($total > 0 ? ' ('.round($v / $total * 100).'%)' : ''), $values), 460, 70);

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
                    // Side, top and front faces.
                    imagefilledpolygon($image, [$x2, $y, $x2 + $depth, $y - $depth, $x2 + $depth, $bottom - $depth, $x2, $bottom], $this->color($image, $s, 0.6));
                    imagefilledpolygon($image, [$x1, $y, $x1 + $depth, $y - $depth, $x2 + $depth, $y - $depth, $x2, $y], $this->color($image, $s, 1.25));
                    imagefilledrectangle($image, $x1, $y, $x2, $bottom, $this->color($image, $s));
                }
                $this->text($image, $this->format($value), 8, ($x1 + $x2) / 2 + $depth / 2, $y - $depth - 4, [30, 41, 59], true, 'center');
                $s++;
            }

            $this->wrapped($image, $category, 8, $left + $groupWidth * $c + $groupWidth / 2, $bottom + 16, (int) $groupWidth - 6);
        }

        $this->legend($image, array_keys($series), array_fill(0, count($series), ''), $left, $height - 34, horizontal: true);

        return $this->dataUri($image);
    }

    private function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageantialias($image, true);

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
    private function legend(GdImage $image, array $labels, array $values, int $x, int $y, bool $horizontal = false): void
    {
        foreach ($labels as $i => $label) {
            imagefilledrectangle($image, $x, $y - 10, $x + 12, $y + 2, $this->color($image, $i));
            $text = $label.(($values[$i] ?? '') !== '' ? ' — '.$values[$i] : '');
            $this->text($image, $text, 9, $x + 18, $y, [30, 41, 59]);
            if ($horizontal) {
                $x += 30 + (int) (strlen($text) * 7);
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
