<?php

namespace App\Support\HarLembar;

/**
 * Jadwal Pemeriksaan Instalasi Blackstart (Jadwal Pemeliharaan, tahunan):
 * per uraian pemeriksaan baris RENCANA & REALISASI, PIC pembuat, tanda per
 * minggu Januari–Desember, JUMLAH & TOTAL, dan rekap bulan terpilih
 * (rencana, realisasi, kinerja + average).
 */
class PemeriksaanBlackstartLembar extends HarLembar
{
    public const MONTHS = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];

    public function key(): string
    {
        return 'pemeriksaan-blackstart';
    }

    public function title(): string
    {
        return 'Jadwal Pemeriksaan Instalasi Blackstart';
    }

    public function description(): string
    {
        return 'Rencana & realisasi pemeriksaan instalasi blackstart per minggu Januari–Desember, PIC pembuat, jumlah, dan kinerja bulan berjalan.';
    }

    public function menu(): string
    {
        return 'jadwal';
    }

    public function yearly(): bool
    {
        return true;
    }

    public function kopLines(string $unitName, int $month, int $year): array
    {
        return [
            'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI',
            'LAPORAN PROJECT '.strtoupper($unitName),
            'JADWAL PEMERIKSAAN INSTALASI BLACKSTART TAHUN '.$year,
        ];
    }

    public function fields(): array
    {
        return [
            self::field('uraian', 'URAIAN PEMERIKSAAN', 'text', 'before', 170),
            self::field('pic', 'PIC PEMBUAT', 'text', 'before', 90, 'c'),
        ];
    }

    public function grid(int $month, int $year): array
    {
        $columns = [];
        foreach (self::MONTHS as $index => $name) {
            foreach (range(1, 4) as $week) {
                $columns[] = ['key' => 'm'.($index + 1).'w'.$week, 'label' => (string) $week, 'group' => $name, 'sub' => null, 'is_red' => false];
            }
        }

        return $columns;
    }

    public function cellType(): string
    {
        return 'mark';
    }

    public function codes(): array
    {
        return ['1' => 'Dijadwalkan / dilaksanakan pada minggu tersebut'];
    }

    public function lines(): array
    {
        return ['rencana' => 'RENCANA', 'realisasi' => 'REALISASI'];
    }

    public function showCount(): bool
    {
        return true;
    }

    public function sections(): array
    {
        return [[
            'key' => 'data-teknis',
            'title' => 'A. PEMBUATAN DATA TEKNIS',
            'items' => [['uraian' => 'ENGINE DIESEL GENSET (EDG)'], ['uraian' => 'SISTEM BATTERY']],
        ]];
    }

    public function summary(array $rows, int $month, int $year): ?array
    {
        $prefix = 'm'.$month.'w';
        $kinerja = fn (int $rencana, int $realisasi): string => $rencana > 0 ? round($realisasi / $rencana * 100).'%' : '-';
        $lines = [];
        $totalRencana = 0;
        $totalRealisasi = 0;

        foreach (array_values($rows) as $index => $row) {
            $rencana = self::count($row['cells']['rencana'] ?? [], $prefix);
            $realisasi = self::count($row['cells']['realisasi'] ?? [], $prefix);
            $totalRencana += $rencana;
            $totalRealisasi += $realisasi;
            $lines[] = [$index + 1, (string) ($row['fields']['uraian'] ?? ''), $rencana, $realisasi, $kinerja($rencana, $realisasi)];
        }
        $lines[] = [count($lines) + 1, 'AVERAGE', $totalRencana, $totalRealisasi, $kinerja($totalRencana, $totalRealisasi)];

        return [
            'title' => 'KINERJA BULAN '.self::MONTHS[$month - 1],
            'columns' => ['NO', 'URAIAN', 'RENCANA', 'REALISASI', 'A. KINERJA'],
            'rows' => $lines,
        ];
    }
}
