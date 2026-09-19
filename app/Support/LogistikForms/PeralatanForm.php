<?php

namespace App\Support\LogistikForms;

/**
 * Laporan Peralatan, Material dan Tools: per item (uraian, merek,
 * ukuran/type) the kondisi (baik / rusak / hilang) on each of the 4
 * pelaksanaan of the month, with rencana, realisasi and kinerja, section
 * totals and the rekapitulasi per section.
 */
class PeralatanForm extends LogistikForm
{
    public const PELAKSANAAN = 4;

    private const KONDISI = ['baik' => 'BAIK', 'rusak' => 'RUSAK', 'hilang' => 'HILANG'];

    public function key(): string
    {
        return 'peralatan';
    }

    public function title(): string
    {
        return 'Laporan Peralatan, Material dan Tools Logistik & Gudang';
    }

    public function kop(): string
    {
        return 'LAPORAN PERALATAN, MATERIAL DAN TOOLS LOGISTIK & GUDANG';
    }

    public function description(): string
    {
        return 'Kondisi peralatan, material dan tools (baik / rusak / hilang) pada 4 kali pelaksanaan dalam sebulan, dengan rencana, realisasi dan kinerja.';
    }

    public function columns(): array
    {
        $kondisi = [];
        foreach (range(1, self::PELAKSANAAN) as $p) {
            foreach (self::KONDISI as $key => $label) {
                $kondisi[] = self::col("p{$p}_{$key}", $label, 'number', ['group' => "Pelaksanaan {$p} — Kondisi", 'align' => 'c', 'width' => 36]);
            }
        }

        return [
            self::col('uraian', 'URAIAN', 'text', ['width' => 140]),
            self::col('merek', 'MEREK', 'text', ['width' => 70]),
            self::col('ukuran', 'UKURAN/TYPE', 'text', ['width' => 70]),
            ...$kondisi,
            self::col('rencana', 'RENCANA', 'computed', ['group' => 'Pelaksanaan', 'align' => 'c', 'width' => 46, 'computed' => ['formula' => (string) self::PELAKSANAAN]]),
            self::col('realisasi', 'REALISASI', 'computed', ['group' => 'Pelaksanaan', 'align' => 'c', 'width' => 50, 'computed' => [
                'count_filled' => array_map(fn (int $p): array => array_map(fn (string $k): string => "p{$p}_{$k}", array_keys(self::KONDISI)), range(1, self::PELAKSANAAN)),
            ]]),
            self::col('kinerja', 'A. KINERJA', 'computed', ['group' => 'Pelaksanaan', 'align' => 'c', 'width' => 50, 'computed' => ['percent' => ['realisasi', 'rencana']]]),
        ];
    }

    public function sections(): array
    {
        $totals = [...$this->kondisiKeys(), 'rencana', 'realisasi'];

        return [
            self::section('peralatan', 'A. REKAPITULASI PERALATAN', [], 10, $totals),
            self::section('material', 'B. REKAPITULASI MATERIAL', [], 11, $totals),
            self::section('tools', 'C. REKAPITULASI TOOLS', [], 8, $totals),
        ];
    }

    public function notes(): array
    {
        return [
            'Isilah tanggal pelaksanaan sesuai jadwal dengan target 4 kali dalam 1 bulan.',
            'Jika hasilnya baik maka isi angka 1, jika hasilnya rusak maka isi angka 1, jika hasilnya hilang maka isi angka 1.',
        ];
    }

    public function summary(array $rows): ?array
    {
        $recap = [];
        foreach ($this->sections() as $index => $section) {
            foreach (self::KONDISI as $key => $label) {
                $sum = 0.0;
                foreach ($rows[$section['key']] ?? [] as $row) {
                    foreach (range(1, self::PELAKSANAAN) as $p) {
                        $sum += $this->number($row["p{$p}_{$key}"] ?? null);
                    }
                }
                $recap[] = [$index + 1, str_replace(['A. ', 'B. ', 'C. '], '', $section['title'] ?? ''), $label, (int) $sum];
            }
        }

        return ['title' => 'REKAPITULASI KONDISI', 'columns' => ['NO', 'REKAPITULASI', 'KONDISI', 'JUMLAH'], 'rows' => $recap];
    }

    /**
     * @return list<string>
     */
    private function kondisiKeys(): array
    {
        return collect(range(1, self::PELAKSANAAN))->flatMap(fn (int $p): array => array_map(fn (string $k): string => "p{$p}_{$k}", array_keys(self::KONDISI)))->all();
    }
}
