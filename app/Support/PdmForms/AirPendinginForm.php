<?php

namespace App\Support\PdmForms;

/**
 * "Laporan Pengukuran Kualitas Air Pendingin": per sampling (tanggal &
 * proses), the electric-tool readings (pH, hardness, conductivity,
 * temperature), Kittiwake CaCO3, the 16 test-strip pads and documentation.
 */
class AirPendinginForm extends PdmForm
{
    public const PROCESSES = ['Air Baku', 'Backwash', 'Rinse', 'Rinse After Regeneration', 'Air Pendingin Mesin'];

    public const TEST_STRIPS = 16;

    public function key(): string
    {
        return 'air-pendingin';
    }

    public function title(): string
    {
        return 'Laporan Pengukuran Kualitas Air Pendingin';
    }

    public function description(): string
    {
        return 'Parameter kualitas air pendingin mesin: pH, hardness, conductivity, temperatur, CaCO3 (Kittiwake), dan test strips.';
    }

    public function kopLines(string $unitName): array
    {
        return ['JASA PENDUKUNG TEKNIS 11 SITE', 'PLN NP UP KENDARI '.strtoupper($unitName), 'LAPORAN PENGUKURAN KUALITAS AIR PENDINGIN', 'BAGIAN PdM PEMBANGKIT'];
    }

    public function headerColor(): string
    {
        return 'cyan';
    }

    public function sections(): array
    {
        $columns = [
            self::col('tanggal', 'Tanggal', 'date', ['width' => 70, 'align' => 'c']),
            self::col('process', 'Process', 'select', ['options' => self::PROCESSES, 'width' => 95]),
            self::col('ph', 'PH', 'text', ['group' => 'Electric Tools', 'unit' => '–', 'align' => 'c']),
            self::col('hardness', 'Hardness', 'text', ['group' => 'Electric Tools', 'unit' => 'ppm', 'align' => 'c']),
            self::col('conductivity', 'Conductivity', 'text', ['group' => 'Electric Tools', 'unit' => 'ms/cm', 'align' => 'c']),
            self::col('temperature', 'Temperature', 'text', ['group' => 'Electric Tools', 'unit' => 'degC', 'align' => 'c']),
            self::col('caco3', 'CaCO3', 'text', ['group' => 'Kittiwake', 'unit' => 'ppm', 'align' => 'c']),
        ];

        for ($i = 1; $i <= self::TEST_STRIPS; $i++) {
            $columns[] = self::col('strip_'.$i, (string) $i, 'text', ['group' => 'Test Strips', 'align' => 'c', 'width' => 20]);
        }

        $columns[] = self::col('documentation', 'Documentation', 'text', ['width' => 110]);

        return [[
            'key' => 'pengukuran',
            'title' => 'PENGUKURAN KUALITAS AIR PENDINGIN',
            'columns' => $columns,
            'blank_rows' => 5,
        ]];
    }
}
