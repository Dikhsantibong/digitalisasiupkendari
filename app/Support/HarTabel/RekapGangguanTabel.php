<?php

namespace App\Support\HarTabel;

/**
 * Rekap Laporan Gangguan (Input Pemeliharaan): per gangguan tanggal kejadian,
 * unit, merk/type, daya, tindakan, material rusak, tanggal operasi, durasi
 * pemeliharaan, komponen & sistem terganggu, kWh loss, tindak lanjut,
 * pencegahan dan status Open/Close.
 */
class RekapGangguanTabel extends HarTabel
{
    /** @var list<string> */
    public const SISTEM = [
        'Sistem Kelistrikan',
        'Sistem Air Pendingin',
        'Sistem Udara Masuk',
        'Sistem Bahan Bakar',
        'Sistem Pelumas',
        'Sistem Gas Buang',
        'Lainnya',
    ];

    /** @var list<string> */
    public const STATUS = ['Open', 'Close'];

    public function key(): string
    {
        return 'laporan-gangguan';
    }

    public function title(): string
    {
        return 'Rekap Laporan Gangguan';
    }

    public function description(): string
    {
        return 'Rekap gangguan pembangkit: tindakan, material rusak, durasi pemeliharaan, komponen & sistem terganggu, kWh loss, tindak lanjut, pencegahan, dan status.';
    }

    public function kopLines(string $unitName, int $month, int $year): array
    {
        return ['JASA PENDUKUNG TEKNIS - 6 SITE UP KENDARI', strtoupper($unitName), 'LAPORAN PROJECT', 'REKAP LAPORAN GANGGUAN'];
    }

    public function columns(): array
    {
        return [
            self::col('tanggal_kejadian', 'TANGGAL KEJADIAN', 'date', 70, 'c'),
            self::col('no_unit', 'NO. UNIT', 'text', 55, 'c'),
            self::col('merk_type', 'MERK/TYPE MESIN', 'text', 70, 'c'),
            self::col('daya_terpasang', 'DAYA TERPASANG (kW)', 'number', 50, 'c'),
            self::col('daya_mampu', 'DAYA MAMPU (kW)', 'number', 50, 'c'),
            self::col('tindakan', 'TINDAKAN', 'textarea', 110),
            self::col('material_rusak', 'MATERIAL YANG RUSAK', 'textarea', 90),
            self::col('tanggal_operasi', 'TANGGAL OPERASI', 'date', 70, 'c'),
            self::col('durasi', 'DURASI PEMELIHARAAN (JAM)', 'number', 55, 'c'),
            self::col('komponen', 'KOMPONEN MESIN YANG TERGANGGU', 'text', 85, 'c'),
            self::col('sistem', 'SISTEM', 'select', 90, 'c', ['options' => self::SISTEM]),
            self::col('kwh_loss', 'KWH LOSS OUTPUT (KWH)', 'number', 50, 'c'),
            self::col('tindak_lanjut', 'TINDAK LANJUT GANGGUAN', 'textarea', 90),
            self::col('pencegahan', 'TINDAKAN PENCEGAHAN TERHADAP PROBLEM BERULANG', 'textarea', 90),
            self::col('status', 'STATUS', 'select', 50, 'c', ['options' => self::STATUS]),
        ];
    }

    public function totals(): array
    {
        return ['durasi', 'kwh_loss'];
    }

    public function summary(array $rows): ?array
    {
        $status = fn (string $value): int => count(array_filter($rows, fn (array $row): bool => ($row['status'] ?? null) === $value));
        $totals = $this->totalValues($rows);

        return [
            'title' => 'REKAP GANGGUAN',
            'columns' => ['JUMLAH GANGGUAN', 'OPEN', 'CLOSE', 'TOTAL DURASI (JAM)', 'TOTAL KWH LOSS'],
            'rows' => [[count($rows), $status('Open'), $status('Close'), $totals['durasi'], $totals['kwh_loss']]],
        ];
    }
}
