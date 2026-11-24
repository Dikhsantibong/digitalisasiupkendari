<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Definisi Program 5S 5R Pemeliharaan (Modul HAR): lima program per shift per hari
 * (Pagi, Sore, Malam) dengan detail & PIC bawaan dari lembar resmi, pilihan tindakan,
 * progres, kondisi, dan foto eviden per shift per hari.
 */
final class HarProgram5s5r
{
    public const DEFAULT_PIC = 'K3L, Operasi Dan Pemeliharaan';

    /** Foto eviden maksimal per shift per hari. */
    public const MAX_EVIDENCE = 3;

    /** @var list<string> */
    public const SHIFTS = ['Pagi', 'Sore', 'Malam'];

    /**
     * @var array<string, array{label: string, istilah: string, detail: string}>
     */
    public const PROGRAMS = [
        'ringkas' => ['label' => 'Ringkas', 'istilah' => 'Seiri', 'detail' => 'Memilah/memisahkan tools dan material antara yang diperlukan dan yang tidak diperlukan'],
        'rapi' => ['label' => 'Rapi', 'istilah' => 'Seiton', 'detail' => 'Menata tools dan material yang diperlukan pada tempat/posisi yang tepat dan mudah di akses'],
        'resik' => ['label' => 'Resik', 'istilah' => 'Seiso', 'detail' => 'Membersihkan area kerja, mesin, dan peralatan secara menyeluruh untuk menghilangkan kotoran dan debu'],
        'rawat' => ['label' => 'Rawat', 'istilah' => 'Seiketsu', 'detail' => 'Mempertahankan kondisi yang sudah dicapai pada tiga tahap sebelumnya (Ringkas, Rapi, Resik) melalui standarisasi dan evaluasi'],
        'rajin' => ['label' => 'Rajin', 'istilah' => 'Shitsuke', 'detail' => 'Membangun kedisiplinan dan kebiasaan pada setiap individu untuk mematuhi prosedur 5S 5R'],
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

    public static function daysInMonth(int $month, int $year): int
    {
        return (int) Carbon::create($year, $month, 1)->daysInMonth;
    }

    /**
     * Jumlah minggu dalam bulan (minggu ke-1 = tanggal 1–7, dst.).
     */
    public static function weeks(int $month, int $year): int
    {
        return (int) ceil(self::daysInMonth($month, $year) / 7);
    }

    /**
     * Total slot rencana per program dalam sebulan (jumlah hari × 3 shift).
     */
    public static function plannedSlots(int $month, int $year): int
    {
        return self::daysInMonth($month, $year) * count(self::SHIFTS);
    }

    public static function slotKey(int $day, string $shift): string
    {
        return "{$day}_{$shift}";
    }

    /**
     * @return array{day: int, shift: string}|null
     */
    public static function parseSlotKey(string|int $key, int $maxDay = 31): ?array
    {
        return OperasiProgram5s5r::parseSlotKey($key, $maxDay);
    }

    /**
     * Baris bawaan satu hari & shift: program, detail & PIC dari lembar resmi; kolom
     * hasil (kondisi, tindakan, progres, jumlah, keterangan) kosong untuk diisi.
     *
     * @return array<string, mixed>
     */
    public static function blankRow(int $day, string $shift, string $program): array
    {
        return [
            'day' => $day,
            'shift' => $shift,
            'minggu' => max(1, (int) ceil($day / 7)),
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
