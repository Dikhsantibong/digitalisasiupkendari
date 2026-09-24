<?php

namespace App\Support;

/**
 * Template defaults and options for Form Laporan Unsafe Action & Unsafe Condition (Kondisi K3).
 * Matches the official PLN NP UP Kendari Form Laporan Unsafe Action dan Unsafe Condition sheet.
 */
final class K3KondisiK3Form
{
    public const KATEGORI = [
        'Unsafe Action',
        'Unsafe Condition',
    ];

    public const STATUS = [
        'Closed',
        'Open',
        'On Progress',
    ];

    /**
     * Builds default blank rows if no rows have been saved yet.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultRows(): array
    {
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $rows[] = [
                'id' => null,
                'no_urut' => (string) $i,
                'periode' => '-',
                'kategori' => $i % 2 === 1 ? 'Unsafe Action' : 'Unsafe Condition',
                'temuan' => '',
                'kondisi' => '',
                'tindak_lanjut' => '',
                'rekomendasi' => '',
                'lokasi' => '',
                'keterangan' => 'Open',
                'eviden_sebelum' => null,
                'eviden_sesudah' => null,
                'sort_order' => $i - 1,
            ];
        }

        return $rows;
    }
}
