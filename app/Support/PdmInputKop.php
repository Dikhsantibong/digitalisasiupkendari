<?php

namespace App\Support;

/**
 * Baris kop dokumen input PdM yang tidak memakai mesin form generik
 * (App\Support\PdmForms): satu sumber untuk kop PDF-nya
 * (resources/views/pdm/input/{input}-pdf.blade.php) dan header di halaman input.
 */
final class PdmInputKop
{
    /**
     * @return array{theme: string, lines: list<string>}
     */
    public static function for(string $input, string $unitName): array
    {
        $unit = 'PLN NP UP KENDARI '.strtoupper($unitName);

        return match ($input) {
            'kesiapan-apd' => ['theme' => 'cyan', 'lines' => ['JASA PENDUKUNG TEKNIS 11 SITE', $unit, 'KESIAPAN APD', 'BAGIAN PdM PEMBANGKIT']],
            'permit-to-work' => ['theme' => 'plain', 'lines' => ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE - KIT', $unit, 'LAPORAN PERMIT TO WORK PEMBANGKIT', 'BAGIAN PdM PEMBANGKIT']],
            'realisasi-prediktif' => ['theme' => 'plain', 'lines' => ['JASA PENDUKUNG TEKNIS 11 SITE', $unit, 'BAGIAN PdM PEMBANGKIT', 'REALISASI PEMELIHARAAN PREDIKTIF BULANAN']],
            'sample-monitoring' => ['theme' => 'navy', 'lines' => ['JASA PENDUKUNG TEKNIS 6 - 11 SITE', $unit, 'FORM MONITORING PEMERIKSAAN & PENGIRIMAN SAMPLE PDM', 'BAGIAN PdM PEMBANGKIT']],
            default => throw new \InvalidArgumentException("Unknown PdM input [{$input}]."),
        };
    }

    /**
     * @return list<string>
     */
    public static function lines(string $input, string $unitName): array
    {
        return self::for($input, $unitName)['lines'];
    }
}
