<?php

namespace App\Support\LogistikForms;

/**
 * Laporan Inventaris Lainnya Logistik & Gudang: per inventaris the uraian,
 * merek, jumlah and satuan, the result of each day's check — N (normal) or
 * T (tidak normal), one tick per day — and the pelaksanaan: target, realisasi
 * (days checked) and kinerja.
 */
class InventarisLainnyaForm extends LogistikForm
{
    public const DAYS = 31;

    public const TARGET = 4;

    /** @var list<string> */
    public const URAIAN = ['MEJA KERJA', 'KURSI KERJA', 'KOMPUTER', 'HT', 'LEMARI ARSIP', 'RAK DOKUMEN'];

    public const BLANK_ROWS = 4;

    public function key(): string
    {
        return 'inventaris-lainnya';
    }

    public function title(): string
    {
        return 'Laporan Inventaris Lainnya Logistik & Gudang';
    }

    public function kop(): string
    {
        return 'LAPORAN INVENTARIS LAINNYA LOGISTIK & GUDANG';
    }

    public function description(): string
    {
        return 'Pemeriksaan harian inventaris lainnya (meja, kursi, komputer, HT, lemari, rak): N normal / T tidak normal per tanggal, target, realisasi dan kinerja.';
    }

    /** One N/T pair per day: 62 tick columns must fit an A4 landscape page. */
    public function compact(): bool
    {
        return true;
    }

    public function columns(): array
    {
        $days = [];
        foreach (range(1, self::DAYS) as $day) {
            foreach (['n' => 'N', 't' => 'T'] as $key => $label) {
                $days[] = self::col("d{$day}_{$key}", $label, 'check', ['group' => (string) $day, 'exclusive' => "d{$day}", 'align' => 'c', 'width' => 10]);
            }
        }

        return [
            self::col('uraian', 'URAIAN', 'text', ['width' => 80]),
            self::col('merek', 'MEREK', 'text', ['width' => 45]),
            self::col('jumlah', 'JML', 'number', ['align' => 'c', 'width' => 24]),
            self::col('satuan', 'SATUAN', 'text', ['align' => 'c', 'width' => 30]),
            ...$days,
            self::col('target', 'TARGET', 'number', ['group' => 'Pelaksanaan', 'align' => 'c', 'width' => 32, 'default' => self::TARGET]),
            self::col('realisasi', 'REALISASI', 'computed', ['group' => 'Pelaksanaan', 'align' => 'c', 'width' => 38, 'computed' => [
                'count_filled' => array_map(fn (int $day): array => ["d{$day}_n", "d{$day}_t"], range(1, self::DAYS)),
            ]]),
            self::col('kinerja', 'A. KINERJA', 'computed', ['group' => 'Pelaksanaan', 'align' => 'c', 'width' => 38, 'computed' => ['percent' => ['realisasi', 'target']]]),
        ];
    }

    public function sections(): array
    {
        return [self::section(
            'inventaris',
            null,
            array_map(fn (string $uraian): array => ['uraian' => $uraian, 'target' => self::TARGET], self::URAIAN),
            self::BLANK_ROWS,
        )];
    }

    public function notes(): array
    {
        return [
            'Kolom tanggal pelaksanaan: centang N bila hasil pemeriksaan normal, T bila tidak normal (satu centang per tanggal).',
            'Realisasi = jumlah tanggal yang sudah diperiksa; kinerja = realisasi dibanding target.',
        ];
    }

    public function summary(array $rows): ?array
    {
        $items = collect($rows)->flatten(1)->filter(fn (array $row): bool => trim((string) ($row['uraian'] ?? '')) !== '');
        $count = fn (string $suffix): int => $items->sum(fn (array $row): int => collect(range(1, self::DAYS))
            ->filter(fn (int $day): bool => (int) ($row["d{$day}_{$suffix}"] ?? 0) === 1)->count());

        return [
            'title' => 'REKAP PEMERIKSAAN INVENTARIS',
            'columns' => ['TOTAL INVENTARIS', 'PEMERIKSAAN NORMAL (N)', 'PEMERIKSAAN TIDAK NORMAL (T)'],
            'rows' => [[$items->count(), $count('n'), $count('t')]],
        ];
    }
}
