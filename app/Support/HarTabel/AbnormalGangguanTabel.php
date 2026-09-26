<?php

namespace App\Support\HarTabel;

use App\Enums\PermissionName;
use App\Support\Indonesian;

/**
 * Laporan Kondisi Abnormal dan Gangguan Pembangkit (Input Pemeliharaan): per
 * kejadian uraian kondisi, jenis gangguan HAR (mesin/electrical/sipil),
 * tanggal, dan status ABNORMAL atau GANGGUAN (isi 1) dengan durasi (jam), plus
 * TOTAL tiap kolom status.
 */
class AbnormalGangguanTabel extends HarTabel
{
    /** @var list<string> */
    public const JENIS = ['MESIN', 'ELECTRICAL', 'SIPIL'];

    public function key(): string
    {
        return 'abnormal-gangguan';
    }

    public function fieldPermission(): ?PermissionName
    {
        return PermissionName::HarLapanganAbnormalGangguan;
    }

    public function title(): string
    {
        return 'Laporan Kondisi Abnormal dan Gangguan Pembangkit';
    }

    public function description(): string
    {
        return 'Kondisi abnormal & gangguan pembangkit per kejadian: uraian, jenis (mesin/electrical/sipil), tanggal, status abnormal/gangguan dan durasinya, serta total.';
    }

    public function kopLines(string $unitName, int $month, int $year): array
    {
        return [
            'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT',
            'LAPORAN PROJECT '.strtoupper($unitName),
            'LAPORAN KONDISI ABNORMAL DAN GANGGUAN PEMBANGKIT',
            strtoupper(Indonesian::monthName($month)).' '.$year,
        ];
    }

    public function columns(): array
    {
        return [
            self::col('uraian', 'URAIAN KONDISI', 'textarea', 170),
            self::col('jenis', 'JENIS GANGGUAN HAR (MESIN/ELECTRICAL/SIPIL)', 'text', 110, 'c'),
            self::col('tanggal', 'TANGGAL', 'date', 80, 'c'),
            self::col('abnormal', 'ABNORMAL', 'check', 70, 'c', ['group' => 'STATUS', 'exclusive' => 'status']),
            self::col('durasi_abnormal', 'DURASI', 'number', 70, 'c', ['group' => 'STATUS']),
            self::col('gangguan', 'GANGGUAN', 'check', 70, 'c', ['group' => 'STATUS', 'exclusive' => 'status']),
            self::col('durasi_gangguan', 'DURASI', 'number', 70, 'c', ['group' => 'STATUS']),
        ];
    }

    public function totals(): array
    {
        return ['abnormal', 'durasi_abnormal', 'gangguan', 'durasi_gangguan'];
    }

    public function notes(): array
    {
        return ['Kolom ABNORMAL dan GANGGUAN diisi angka 1 (centang salah satu).', 'Kolom DURASI diisi jam.'];
    }
}
