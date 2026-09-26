<?php

namespace App\Support\HarLembar;

use App\Enums\PermissionName;
use App\Support\Indonesian;

/**
 * Laporan Patrol Check Pemeliharaan (Input Pemeliharaan, bulanan, per mesin):
 * per peralatan tiap sistem hasil pemeriksaan harian N (normal) atau T (tidak
 * normal), akhir pekan & hari libur merah, dengan catatan (Note).
 */
class PatrolCheckPemeliharaanLembar extends HarLembar
{
    /** @var array<string, array{0: string, 1: list<string>}> */
    public const SYSTEMS = [
        'lubricating' => ['LUBRICATING SYSTEM', ['Lube Oil Line Pipe', 'Oil Line STC', 'Hosing T/C', 'Oil Filter Line', 'Breather Smoke', 'Seal Crankshaft']],
        'fuel' => ['FUEL SYSTEM', ['Fuel Line Pipe', 'Fuel Daily Tank', 'Fuel Flow Meter', 'PT Pump', 'Fuel Filter Line', 'Fuel Line STC']],
        'cooling' => ['COOLING SYSTEM', ['Water Line Pipe', 'Radiator Core', 'Radiator Hose & Pipe', 'Water Pump', 'Water Filter Line', 'Housing Thermostat']],
        'air-exhaust' => ['AIR INTAKE & EXHAUST SYSTEM', ['Air Intake Manifold', 'Air Intake Hose', 'Turbo Charger', 'Exhaust Manifold', 'Muffler Smoke']],
        'electrical' => ['ELECTRICAL SYSTEM', ['Battery', 'HMI Panel', 'GCP Panel', 'Transformer', 'Alternator Charging', 'Cubicle']],
    ];

    public function key(): string
    {
        return 'patrol-check-pemeliharaan';
    }

    public function fieldPermission(): ?PermissionName
    {
        return PermissionName::HarLapanganPatrolCheckPemeliharaan;
    }

    public function title(): string
    {
        return 'Laporan Patrol Check Pemeliharaan';
    }

    public function description(): string
    {
        return 'Patrol check harian per mesin: peralatan tiap sistem (pelumasan, bahan bakar, pendingin, udara & gas buang, kelistrikan) N normal / T tidak normal per tanggal.';
    }

    public function menu(): string
    {
        return 'input';
    }

    public function perMachine(): bool
    {
        return true;
    }

    public function kopLines(string $unitName, int $month, int $year): array
    {
        return [
            'JASA PENDUKUNG TEKNIS 6 KIT',
            'LAPORAN PROJECT '.strtoupper($unitName),
            'PATROL CHECK PEMELIHARAAN',
            strtoupper(Indonesian::monthName($month)).' '.$year,
        ];
    }

    public function fields(): array
    {
        return [self::field('peralatan', 'PERALATAN', 'text', 'before', 130)];
    }

    public function grid(int $month, int $year): array
    {
        return self::days($month, $year);
    }

    public function cellType(): string
    {
        return 'pair';
    }

    public function codes(): array
    {
        return ['N' => 'Normal', 'T' => 'Tidak normal'];
    }

    public function noteLabel(): ?string
    {
        return 'Note';
    }

    public function sections(): array
    {
        return array_map(fn (string $key, array $system): array => [
            'key' => $key,
            'title' => $system[0],
            'items' => array_map(fn (string $item): array => ['peralatan' => $item], $system[1]),
        ], array_keys(self::SYSTEMS), self::SYSTEMS);
    }

    public function summary(array $rows, int $month, int $year): ?array
    {
        $bySection = collect($rows)->groupBy('section');
        $count = fn ($sectionRows, string $code): int => collect($sectionRows)->sum(fn (array $row): int => count(array_filter($row['cells']['main'] ?? [], fn (string $value): bool => $value === $code)));

        $lines = [];
        foreach (self::SYSTEMS as $key => $system) {
            $sectionRows = $bySection->get($key, collect());
            $lines[] = [$system[0], $count($sectionRows, 'N'), $count($sectionRows, 'T')];
        }
        $lines[] = ['TOTAL', collect($lines)->sum(1), collect($lines)->sum(2)];

        return [
            'title' => 'REKAP HASIL PATROL',
            'columns' => ['SISTEM', 'NORMAL (N)', 'TIDAK NORMAL (T)'],
            'rows' => $lines,
        ];
    }
}
