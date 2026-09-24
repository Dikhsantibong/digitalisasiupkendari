<?php

namespace App\Support\HarLembar;

use App\Support\Indonesian;

/**
 * Jadwal Inventarisasi Tools & Material (Jadwal Pemeliharaan, bulanan): per
 * tools uraian, merek, penerimaan, satuan, jumlah dan kondisi tiap minggu —
 * 1 baik / 2 tidak baik / 3 rusak — serta keterangan.
 */
class InventarisasiToolsLembar extends HarLembar
{
    /** @var list<array{0: string, 1: string}> uraian, merek */
    public const TOOLS = [
        ['Kunci Shock Set 8 - 32 mm', 'TEKIRO'],
        ['Kunci Shock Set 1/4"', ''],
        ['Kunci Pas Ring 8-24 mm', ''],
        ['Kunci Inggris 12"', 'TEKIRO'],
        ['Kunci L Set', ''],
        ['Tang Kombinasi', 'TEKIRO'],
        ['Tang Lancip', ''],
        ['Tang Potong', ''],
        ['Fuller Gauge', ''],
        ['Bor Tangan', ''],
        ['Gerinda Tangan', 'MAKTEK'],
        ['Mesin Las 900 Watt', 'LAKONI'],
        ['Palu Plastik', ''],
        ['Palu Besi', ''],
        ['Gergaji Besi', ''],
        ['Cutter', ''],
        ['Meteran', ''],
        ['Clamp Meter (Tang Ampere)', 'TEKIRO'],
        ['Tespen', 'TEKIRO'],
    ];

    public const WEEKS = 4;

    public function key(): string
    {
        return 'inventarisasi-tools';
    }

    public function title(): string
    {
        return 'Jadwal Inventarisasi Tools & Material';
    }

    public function description(): string
    {
        return 'Inventarisasi tools & material per minggu (1 baik, 2 tidak baik, 3 rusak) dengan merek, penerimaan, satuan, jumlah dan keterangan.';
    }

    public function menu(): string
    {
        return 'jadwal';
    }

    public function kopLines(string $unitName, int $month, int $year): array
    {
        return [
            'JASA PENDUKUNG TEKNIK 6 SITE UP KENDARI',
            strtoupper($unitName),
            'LAPORAN PROJECT',
            'JADWAL INVENTARISASI TOOLS & MATERIAL BULAN '.strtoupper(Indonesian::monthName($month)).' '.$year,
        ];
    }

    public function fields(): array
    {
        return [
            self::field('uraian', 'URAIAN', 'text', 'before', 170),
            self::field('merek', 'MEREK', 'text', 'before', 80, 'c'),
            self::field('penerimaan', 'PENERIMAAN', 'text', 'before', 70, 'c'),
            self::field('satuan', 'SATUAN', 'text', 'before', 45, 'c'),
            self::field('jumlah', 'JUMLAH', 'number', 'before', 45, 'c'),
            self::field('keterangan', 'KETERANGAN', 'text', 'after', 130),
        ];
    }

    public function grid(int $month, int $year): array
    {
        return array_map(fn (int $week): array => ['key' => "w{$week}", 'label' => "WEEK {$week}", 'group' => null, 'sub' => null, 'is_red' => false], range(1, self::WEEKS));
    }

    public function cellType(): string
    {
        return 'code';
    }

    public function codes(): array
    {
        return ['1' => 'baik', '2' => 'tidak baik', '3' => 'rusak'];
    }

    public function legendTitle(): string
    {
        return 'Catatan Pengisian';
    }

    public function sections(): array
    {
        return [[
            'key' => 'tools',
            'title' => null,
            'items' => array_map(fn (array $tool): array => ['uraian' => $tool[0], 'merek' => $tool[1], 'satuan' => 'Bh', 'jumlah' => 1], self::TOOLS),
        ]];
    }

    public function summary(array $rows, int $month, int $year): ?array
    {
        $count = fn (string $code): int => collect($rows)->sum(fn (array $row): int => count(array_filter($row['cells']['main'] ?? [], fn (string $value): bool => $value === $code)));

        return [
            'title' => 'REKAP KONDISI (JUMLAH PEMERIKSAAN MINGGUAN)',
            'columns' => ['JUMLAH TOOLS', '1 BAIK', '2 TIDAK BAIK', '3 RUSAK'],
            'rows' => [[count($rows), $count('1'), $count('2'), $count('3')]],
        ];
    }
}
