<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Definisi Jadwal Program 5S 5R Pengoperasian KIT (Modul OPERASI):
 * lima program per minggu dengan detail & PIC bawaan dari lembar resmi,
 * pilihan tindakan, progres dan kondisi. Satu sumber untuk halaman input, validasi dan PDF.
 */
final class OperasiProgram5s5r
{
    public const DEFAULT_PIC = 'K3L, Operasi Dan Pemeliharaan';

    /** Foto eviden maksimal per minggu. */
    public const MAX_EVIDENCE = 3;

    /**
     * @var array<string, array{label: string, istilah: string, detail: string}>
     */
    public const PROGRAMS = [
        'ringkas' => [
            'label' => 'Ringkas',
            'istilah' => 'Seiri',
            'detail' => 'Memilah barang dan memisahkan antara yang diperlukan dan yang tidak diperlukan',
        ],
        'rapi' => [
            'label' => 'Rapi',
            'istilah' => 'Seiton',
            'detail' => 'Menata barang yang diperlukan pada posisi yang tepat dan mudah di akses',
        ],
        'resik' => [
            'label' => 'Resik',
            'istilah' => 'Seiso',
            'detail' => 'Membersihkan area kerja, mesin, dan peralatan secara menyeluruh untuk menghilangkan kotoran dan debu',
        ],
        'rawat' => [
            'label' => 'Rawat',
            'istilah' => 'Seiketsu',
            'detail' => 'Mempertahankan kondisi yang sudah dicapai pada tiga tahap sebelumnya (Ringkas, Rapi, Resik) melalui standarisasi dan evaluasi rutin',
        ],
        'rajin' => [
            'label' => 'Rajin',
            'istilah' => 'Shitsuke',
            'detail' => 'Membangun kedisiplinan dan kebiasaan pada setiap individu untuk mematuhi prosedur 5S 5R',
        ],
    ];

    /**
     * Kolom tindakan (boolean) => judul kolom.
     *
     * @var array<string, string>
     */
    public const TINDAKAN = [
        'membersihkan' => 'Membersihkan',
        'merapikan' => 'Merapikan',
        'membuang_sampah' => 'Membuang sampah',
        'mengecat' => 'Mengecat',
        'lainnya' => 'Lainnya',
    ];

    /** @var list<string> */
    public const PROGRES = ['0-25', '26-50', '51-75', '76-100'];

    /** @var list<string> */
    public const KONDISI = ['Baik', 'Kurang Baik', 'Rusak'];

    /**
     * Jumlah minggu dalam bulan (minggu ke-1 = tanggal 1–7, dst.).
     */
    public static function weeks(int $month, int $year): int
    {
        return (int) ceil(Carbon::create($year, $month, 1)->daysInMonth / 7);
    }

    /**
     * Baris bawaan satu minggu: program, detail & PIC dari lembar resmi; kolom
     * hasil (kondisi, tindakan, progres, jumlah, keterangan) kosong untuk diisi.
     *
     * @return array<string, mixed>
     */
    public static function blankRow(int $minggu, string $program): array
    {
        return [
            'minggu' => $minggu,
            'program' => $program,
            'detail' => self::PROGRAMS[$program]['detail'],
            'pic' => self::DEFAULT_PIC,
            'kondisi_awal' => null,
            ...array_fill_keys(array_keys(self::TINDAKAN), false),
            'progres' => null,
            'kondisi_akhir' => null,
            'jumlah' => null,
            'keterangan' => null,
            'saved' => false,
        ];
    }

    /**
     * Apakah baris berisi hasil pelaksanaan (bukan sekadar teks bawaan).
     *
     * @param  array<string, mixed>  $row
     */
    public static function isFilled(array $row): bool
    {
        foreach (['kondisi_awal', 'progres', 'kondisi_akhir', 'jumlah', 'keterangan'] as $key) {
            if (($row[$key] ?? null) !== null && trim((string) $row[$key]) !== '') {
                return true;
            }
        }

        foreach (array_keys(self::TINDAKAN) as $key) {
            if (! empty($row[$key])) {
                return true;
            }
        }

        return false;
    }
}
