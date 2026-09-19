<?php

namespace App\Support\PdmForms;

use App\Models\Machine;
use App\Models\Unit;

/**
 * "Pengukuran Kualitas Pelumas" per machine: the lab parameters per sampling
 * (TBN, water content, viscosity 40/100, AW additive, glycol, nitration,
 * oxidation, soot, sulfation), status, standard, sample photos, analisa, CBA
 * and rekomendasi, signed by Project Leader, Koordinator & Officer PdM.
 */
class PelumasForm extends PdmForm
{
    public function key(): string
    {
        return 'pelumas';
    }

    public function title(): string
    {
        return 'Laporan Pengukuran Kualitas Pelumas';
    }

    public function description(): string
    {
        return 'Uji laboratorium pelumas per mesin (TBN, water content, viskositas, aditif, nitrasi, soot, dan lain-lain), analisa, CBA, dan rekomendasi.';
    }

    public function kopLines(string $unitName): array
    {
        return ['JASA PENDUKUNG TEKNIS 11 SITE', 'PLN NP UP KENDARI '.strtoupper($unitName), 'BAGIAN PdM PEMBANGKIT', 'PENGUKURAN KUALITAS PELUMAS'];
    }

    public function headerColor(): string
    {
        return 'navy';
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
            self::field('no_dokumen', 'Nomor Dokumen', group: 'DOKUMEN'),
            self::field('revisi', 'Revisi', group: 'DOKUMEN'),
            self::field('tanggal_terbit', 'Tanggal Terbit', group: 'DOKUMEN'),
            self::field('unit_sentral', 'Unit/Sentral', group: 'SAMPEL'),
            self::field('mesin', 'Mesin', group: 'SAMPEL'),
            self::field('no_seri', 'No. Seri', group: 'SAMPEL'),
            self::field('titik_sampel', 'Titik Sampel', group: 'SAMPEL'),
            self::field('status', 'Status', 'textarea', 'footer'),
            self::field('standard', 'Standard', 'textarea', 'footer'),
            self::field('foto', 'Foto Sampel Pelumas', 'images', 'footer'),
            self::field('analisa', 'Analisa', 'textarea', 'footer'),
            self::field('cba', 'CBA', 'textarea', 'footer'),
            self::field('rekomendasi', 'Rekomendasi', 'textarea', 'footer'),
        ];
    }

    public function defaults(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return [
            'no_dokumen' => 'FMKD-309-14.2.3.b-A2',
            'unit_sentral' => $unit->name,
            'mesin' => (string) ($machine?->name ?? ''),
            'titik_sampel' => 'Sump Tank',
            'standard' => 'Water Content : 2000 ppm',
        ];
    }

    public function sections(): array
    {
        return [[
            'key' => 'parameter',
            'title' => 'PARAMETER',
            'blank_rows' => 1,
            'columns' => [
                self::col('tanggal', 'Tanggal', 'date', ['align' => 'c', 'width' => 62]),
                self::col('tbn', 'TBN', 'text', ['unit' => 'mgKOH/g', 'align' => 'c']),
                self::col('water_content', 'Water Content', 'text', ['unit' => 'ppm', 'align' => 'c']),
                self::col('viscosity_40', '40', 'text', ['group' => 'Viscosity (derajat)', 'align' => 'c']),
                self::col('viscosity_100', '100', 'text', ['group' => 'Viscosity (derajat)', 'align' => 'c']),
                self::col('aw_additive', 'A/W Additive', 'text', ['unit' => '%', 'align' => 'c']),
                self::col('glycol', 'Glycol', 'text', ['unit' => '%', 'align' => 'c']),
                self::col('nitration', 'Nitration', 'text', ['unit' => 'abs/0.1mm', 'align' => 'c']),
                self::col('oxidation', 'Oxidation', 'text', ['unit' => 'abs/0.1mm', 'align' => 'c']),
                self::col('soot', 'Soot', 'text', ['unit' => '%wt', 'align' => 'c']),
                self::col('sulfation', 'Sulfation', 'text', ['unit' => 'abs/1m', 'align' => 'c']),
                self::col('keterangan', 'Keterangan', 'text', ['width' => 70]),
            ],
        ]];
    }
}
