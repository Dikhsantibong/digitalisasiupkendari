<?php

namespace App\Services\Reports;

use GdImage;

/**
 * The cover of the Laporan SLA gabungan (the user's cover, 2026-11-17): the
 * decorated page background (public/images/sla/cover-bg.png) with the PLN NP
 * & MKP logos, the title "LAPORAN SERVICE LEVEL AGREEMENT (SLA)" and the box
 * Nama Pembangkit / Periode Pelaporan / Tahun, drawn as one A4 portrait PNG
 * (1240 × 1754, 150 dpi) — the same image for the PDF and the Excel.
 */
class SlaCover
{
    private const WIDTH = 1240;

    private const HEIGHT = 1754;

    /** The background was traced on a 616 × 846 page. */
    private const SX = self::WIDTH / 616;

    private const SY = self::HEIGHT / 846;

    private const NAVY = [30, 41, 92];

    private const BLUE = [76, 129, 190];

    private const PURPLE = [96, 77, 130];

    public function png(string $unitName, string $monthName, int $year): string
    {
        $image = imagecreatefrompng(public_path('images/sla/cover-bg.png')) ?: imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imageantialias($image, true);
        imagealphablending($image, true);

        $this->logo($image, public_path('logo/sidebar-logo.png'), 138, 80, 200, 78);
        imagefilledrectangle($image, $this->x(352), $this->y(98), $this->x(354.5), $this->y(158), $this->color($image, self::BLUE));
        $this->logo($image, public_path('logo/mkp.jpg'), 380, 98, 120, 50);

        foreach ([['LAPORAN SERVICE', 255], ['LEVEL AGREEMENT', 313], ['(SLA)', 377]] as [$line, $y]) {
            $this->text($image, $line, 308, $y, 27, self::NAVY, bold: true, center: true);
        }
        imagefilledrectangle($image, $this->x(150), $this->y(343.5), $this->x(455), $this->y(345.5), $this->color($image, self::BLUE));

        $this->roundedBox($image, 62, 406, 562, 513, 14);
        foreach ([['NAMA PEMBANGKIT', mb_strtoupper($unitName)], ['PERIODE PELAPORAN', 'BULAN '.mb_strtoupper($monthName)], ['TAHUN', (string) $year]] as $i => [$label, $value]) {
            $y = 434 + $i * 21;
            $this->text($image, $label, 77, $y, 10.5, self::NAVY);
            $this->text($image, ': '.$value, 312, $y, 10.5, self::NAVY);
        }

        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function x(float $x): int
    {
        return (int) round($x * self::SX);
    }

    private function y(float $y): int
    {
        return (int) round($y * self::SY);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function color(GdImage $image, array $rgb): int
    {
        return imagecolorallocate($image, ...$rgb);
    }

    /** A logo fitted (aspect kept) into the box, left-aligned and centred vertically. */
    private function logo(GdImage $image, string $path, float $x, float $y, float $width, float $height): void
    {
        if (! is_file($path)) {
            return;
        }

        $logo = str_ends_with($path, '.jpg') ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if ($logo === false) {
            return;
        }

        [$w, $h] = [imagesx($logo), imagesy($logo)];
        $boxW = $this->x($width) - $this->x(0);
        $boxH = $this->y($height) - $this->y(0);
        $scale = min($boxW / $w, $boxH / $h);
        $dw = (int) round($w * $scale);
        $dh = (int) round($h * $scale);
        imagecopyresampled($image, $logo, $this->x($x), $this->y($y) + intdiv($boxH - $dh, 2), 0, 0, $dw, $dh, $w, $h);
        imagedestroy($logo);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function text(GdImage $image, string $text, float $x, float $baseline, float $size, array $rgb, bool $bold = false, bool $center = false): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/'.($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));
        $points = $size * self::SX;
        $left = $this->x($x);
        if ($center) {
            $box = imagettfbbox($points, 0, $font, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            $left -= intdiv($box[2] - $box[0], 2);
        }

        imagettftext($image, $points, 0, $left, $this->y($baseline), $this->color($image, $rgb), $font, $text);
    }

    /** The purple rounded frame of the identity box (white inside). */
    private function roundedBox(GdImage $image, float $x1, float $y1, float $x2, float $y2, float $radius): void
    {
        [$l, $t, $r, $b] = [$this->x($x1), $this->y($y1), $this->x($x2), $this->y($y2)];
        $rad = $this->x($radius);
        $border = 4;
        $this->filledRounded($image, $l, $t, $r, $b, $rad, $this->color($image, self::PURPLE));
        $this->filledRounded($image, $l + $border, $t + $border, $r - $border, $b - $border, $rad - $border, $this->color($image, [255, 255, 255]));
    }

    private function filledRounded(GdImage $image, int $l, int $t, int $r, int $b, int $rad, int $color): void
    {
        imagefilledrectangle($image, $l + $rad, $t, $r - $rad, $b, $color);
        imagefilledrectangle($image, $l, $t + $rad, $r, $b - $rad, $color);
        foreach ([[$l + $rad, $t + $rad], [$r - $rad, $t + $rad], [$l + $rad, $b - $rad], [$r - $rad, $b - $rad]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, $rad * 2, $rad * 2, $color);
        }
    }
}
