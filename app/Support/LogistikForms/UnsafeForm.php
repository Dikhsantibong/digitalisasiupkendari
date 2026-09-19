<?php

namespace App\Support\LogistikForms;

/**
 * Laporan Unsafe Action dan Unsafe Condition: per week the kategori, temuan,
 * kondisi, tindak lanjut, rekomendasi, lokasi, keterangan and the eviden
 * photos before & after, with the count of temuan per kategori.
 */
class UnsafeForm extends LogistikForm
{
    public const KATEGORI = ['UNSAFE ACTION', 'UNSAFE CONDITION'];

    public function key(): string
    {
        return 'unsafe';
    }

    public function title(): string
    {
        return 'Laporan Unsafe Action dan Unsafe Condition';
    }

    public function kop(): string
    {
        return 'LAPORAN UNSAFE ACTION DAN UNSAFE CONDITION';
    }

    public function description(): string
    {
        return 'Temuan unsafe action & unsafe condition per minggu dengan kondisi, tindak lanjut, rekomendasi, lokasi dan eviden foto sebelum/sesudah.';
    }

    public function columns(): array
    {
        $text = ['width' => 95];

        return [
            self::col('periode', 'PERIODE', 'select', ['options' => array_map(fn (int $w): string => "MINGGU KE - {$w}", range(1, 5)), 'align' => 'c', 'width' => 70]),
            self::col('kategori', 'KATEGORI', 'select', ['options' => self::KATEGORI, 'align' => 'c', 'width' => 90]),
            self::col('temuan', 'TEMUAN', 'textarea', $text),
            self::col('kondisi', 'KONDISI', 'textarea', $text),
            self::col('tindak_lanjut', 'TINDAK LANJUT', 'textarea', $text),
            self::col('rekomendasi', 'REKOMENDASI', 'textarea', $text),
            self::col('lokasi', 'LOKASI', 'textarea', ['width' => 75]),
            self::col('keterangan', 'KETERANGAN', 'textarea', ['width' => 75]),
            self::col('eviden_sebelum', 'SEBELUM', 'image', ['group' => 'EVIDEN', 'width' => 95]),
            self::col('eviden_sesudah', 'SESUDAH', 'image', ['group' => 'EVIDEN', 'width' => 95]),
        ];
    }

    public function rowHeight(): int
    {
        return 70;
    }

    public function sections(): array
    {
        return [self::section('temuan', null, array_map(fn (int $w): array => ['periode' => "MINGGU KE - {$w}", 'kategori' => 'UNSAFE CONDITION'], range(1, 4)), 2)];
    }

    public function notes(): array
    {
        return ['Jumlah temuan kondisi dihitung dari baris yang berisi temuan sesuai kategorinya.'];
    }

    public function summary(array $rows): ?array
    {
        $all = collect($rows)->flatten(1)->filter(fn (array $row): bool => trim((string) ($row['temuan'] ?? '')) !== '');

        return [
            'title' => 'TEMUAN KONDISI',
            'columns' => ['NO', 'UNSAFE ACTION', 'UNSAFE CONDITION'],
            'rows' => [[1, $all->where('kategori', 'UNSAFE ACTION')->count(), $all->where('kategori', 'UNSAFE CONDITION')->count()]],
        ];
    }
}
