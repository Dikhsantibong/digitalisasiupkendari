<?php

namespace App\Support\PdmForms;

use App\Models\Machine;
use App\Models\Unit;

/**
 * "Laporan Pengukuran Vibrasi Mesin & Generator" per machine: the machine
 * identity, vertical & horizontal max/min/avg (mm/s) at the 15 bearing points
 * A1-C2 (generator), D1-F2 (mesin) and G1-G3 (coupling), standard & kesimpulan.
 */
class VibrasiForm extends PdmForm
{
    public const POINTS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D1', 'D2', 'E1', 'E2', 'F1', 'F2', 'G1', 'G2', 'G3'];

    public function key(): string
    {
        return 'vibrasi';
    }

    public function title(): string
    {
        return 'Laporan Pengukuran Vibrasi Mesin & Generator';
    }

    public function description(): string
    {
        return 'Pengukuran getaran bearing mesin & generator arah vertikal dan horizontal (titik A1-C2, D1-F2, G1-G3) per mesin.';
    }

    public function kopLines(string $unitName): array
    {
        return ['JASA PENDUKUNG TEKNIS 6 SITE', 'PLN NP UP KENDARI '.strtoupper($unitName), 'LAPORAN PENGUKURAN VIBRASI MESIN & GENERATOR', 'BAGIAN PdM PEMBANGKIT'];
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function perMachine(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            self::field('no_dokumen', 'No. Dokumen', group: 'DOKUMEN'),
            self::field('revisi', 'Revisi', group: 'DOKUMEN'),
            self::field('tanggal_dokumen', 'Tanggal', group: 'DOKUMEN'),
            self::field('merek', 'Merek', group: 'MESIN'),
            self::field('type', 'Type', group: 'MESIN'),
            self::field('daya_terpasang', 'Daya Terpasang', group: 'MESIN'),
            self::field('daya_mampu', 'Daya Mampu', group: 'MESIN'),
            self::field('no_seri', 'No. Seri', group: 'MESIN'),
            self::field('mesin_no', 'Mesin No', group: 'MESIN'),
            self::field('rpm', 'RPM', group: 'MESIN'),
            self::field('tanggal_ukur', 'Tanggal Pengukuran', 'date', group: 'MESIN'),
            self::field('standar', 'Standar', 'text', 'footer'),
            self::field('max', 'Max', 'text', 'footer'),
            self::field('kesimpulan', 'Kesimpulan', 'textarea', 'footer'),
        ];
    }

    public function defaults(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return [
            'no_dokumen' => 'FMKD-314-10.3.3.a-B10',
            'revisi' => '03',
            'type' => (string) ($machine?->type ?? ''),
            'mesin_no' => (string) ($machine?->name ?? ''),
            'standar' => 'ISO 10816',
        ];
    }

    public function sections(): array
    {
        return [[
            'key' => 'titik',
            'title' => 'HASIL PENGUKURAN (mm/s)',
            'fixed' => true,
            'columns' => [
                self::col('titik', 'Titik Pengukuran', 'readonly', ['align' => 'c', 'width' => 90]),
                self::col('v_max', 'Max', 'text', ['group' => 'Vertical', 'align' => 'c']),
                self::col('v_min', 'Min', 'text', ['group' => 'Vertical', 'align' => 'c']),
                self::col('v_avg', 'Avg', 'text', ['group' => 'Vertical', 'align' => 'c']),
                self::col('h_max', 'Max', 'text', ['group' => 'Horizontal', 'align' => 'c']),
                self::col('h_min', 'Min', 'text', ['group' => 'Horizontal', 'align' => 'c']),
                self::col('h_avg', 'Avg', 'text', ['group' => 'Horizontal', 'align' => 'c']),
                self::col('keterangan', 'Keterangan', 'text', ['width' => 120]),
            ],
            'rows' => array_map(fn (string $point): array => ['titik' => $point], self::POINTS),
        ]];
    }
}
