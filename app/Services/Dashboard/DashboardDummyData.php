<?php

namespace App\Services\Dashboard;

use App\Support\Indonesian;
use Illuminate\Support\Carbon;

/**
 * Placeholder figures for the dashboard while the per-module aggregations are
 * wired up. Values are deterministic (hashed from a key, never random) so the
 * charts stay stable between refreshes. Every payload shape here is the
 * contract the dashboard page renders, so real queries can later replace one
 * module at a time without touching the frontend.
 */
class DashboardDummyData
{
    private const FALLBACK_UNITS = ['PLTD Poasia', 'PLTD Wangi-Wangi', 'PLTD Raha', 'PLTD Bau-Bau', 'PLTD Kolaka'];

    /** @var list<string> */
    private array $unitNames;

    /**
     * @param  list<string>  $unitNames
     */
    public function __construct(private readonly Carbon $now, array $unitNames = [])
    {
        $names = array_values(array_slice($unitNames, 0, 6));
        $this->unitNames = $names === [] ? self::FALLBACK_UNITS : $names;
    }

    /**
     * @return array<string, mixed>
     */
    public function operasi(): array
    {
        $day = $this->now->day;
        $labels = array_map('strval', range(1, $day));
        $production = array_map(fn (int $d): float => round(410 + 90 * $this->noise('op-prod', $d) + 25 * sin($d / 3), 1), range(1, $day));
        $peak = array_map(fn (int $d): float => round(21 + 4.5 * $this->noise('op-peak', $d) + sin($d / 4), 2), range(1, $day));

        return [
            'kpis' => [
                $this->kpi('produksi', 'Produksi Energi', array_sum($production), 'MWh', 'number', 6.4, 'up', 'op-k1'),
                $this->kpi('beban', 'Beban Puncak', max($peak), 'MW', 'decimal', 2.1, 'up', 'op-k2'),
                $this->kpi('sfc', 'SFC Rata-rata', 0.252, 'L/kWh', 'decimal3', -1.8, 'down', 'op-k3'),
                $this->kpi('eaf', 'EAF', 92.4, '%', 'decimal', 1.2, 'up', 'op-k4'),
                $this->kpi('bbm', 'Pemakaian BBM', 3184.6, 'kL', 'decimal', 4.9, 'down', 'op-k5'),
            ],
            'daily' => [
                'labels' => $labels,
                'series' => [
                    ['name' => 'Produksi (MWh)', 'values' => $production],
                    ['name' => 'Beban Puncak (MW)', 'values' => $peak],
                ],
            ],
            'engine_status' => [
                ['label' => 'Operasi', 'value' => 18],
                ['label' => 'Standby', 'value' => 7],
                ['label' => 'Pemeliharaan', 'value' => 3],
                ['label' => 'Gangguan', 'value' => 1],
            ],
            'gauges' => [
                ['label' => 'EAF', 'value' => 92.4, 'target' => 90, 'good' => 'up'],
                ['label' => 'EFOR', 'value' => 3.1, 'target' => 5, 'good' => 'down'],
                ['label' => 'Logsheet Lengkap', 'value' => 87.5, 'target' => 95, 'good' => 'up'],
            ],
            'fuel_monthly' => $this->monthly('op-fuel', 2900, 600),
            'units' => array_map(fn (string $name, int $i): array => [
                'unit' => $name,
                'daya_mampu' => round(8 + 10 * $this->noise('op-dm', $i), 1),
                'beban_puncak' => round(6 + 8 * $this->noise('op-bp', $i), 1),
                'produksi' => round(1800 + 3200 * $this->noise('op-pr', $i)),
                'sfc' => round(0.238 + 0.03 * $this->noise('op-sfc', $i), 3),
                'eaf' => round(84 + 14 * $this->noise('op-eaf', $i), 1),
                ...$this->pickStatus($i, [['Normal', 'success'], ['Normal', 'success'], ['Siaga', 'warning'], ['Normal', 'success'], ['Defisit', 'danger']]),
            ], $this->unitNames, array_keys($this->unitNames)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function har(): array
    {
        $months = $this->monthLabels();
        $wo = array_map(fn (int $i): int => (int) round(38 + 26 * $this->noise('har-wo', $i)), range(0, 5));
        $sr = array_map(fn (int $i): int => (int) round(24 + 18 * $this->noise('har-sr', $i)), range(0, 5));
        $units = $this->unitNames;

        return [
            'kpis' => [
                $this->kpi('wo', 'Work Order', end($wo), null, 'number', 8.3, 'up', 'har-k1'),
                $this->kpi('wo_closed', 'WO Selesai', 78.6, '%', 'decimal', 3.4, 'up', 'har-k2'),
                $this->kpi('sr_open', 'SR Terbuka', 14, null, 'number', -12.5, 'down', 'har-k3'),
                $this->kpi('cost', 'Biaya Pemeliharaan', 486_750_000, null, 'rupiah', 5.2, 'down', 'har-k4'),
                $this->kpi('mttr', 'MTTR', 6.8, 'jam', 'decimal', -9.1, 'down', 'har-k5'),
            ],
            'trend' => [
                'labels' => $months,
                'series' => [
                    ['name' => 'Work Order', 'values' => $wo],
                    ['name' => 'Service Request', 'values' => $sr],
                ],
            ],
            'cost' => [
                'labels' => $months,
                'series' => [
                    ['name' => 'Jasa', 'values' => array_map(fn (int $i): int => (int) round((140 + 90 * $this->noise('har-cj', $i)) * 1_000_000), range(0, 5))],
                    ['name' => 'Material', 'values' => array_map(fn (int $i): int => (int) round((190 + 150 * $this->noise('har-cm', $i)) * 1_000_000), range(0, 5))],
                ],
            ],
            'by_type' => [
                ['label' => 'Preventive', 'value' => 46],
                ['label' => 'Corrective', 'value' => 21],
                ['label' => 'Predictive', 'value' => 12],
                ['label' => 'Overhaul', 'value' => 4],
            ],
            'by_status' => [
                ['label' => 'Selesai', 'value' => 65],
                ['label' => 'Dikerjakan', 'value' => 11],
                ['label' => 'Tunggu Material', 'value' => 5],
                ['label' => 'Terbuka', 'value' => 2],
            ],
            'backlog' => [
                ['code' => 'WO-2609-041', 'unit' => $units[0], 'asset' => 'Mesin #3 MAN 18V', 'task' => 'Penggantian nozzle injector silinder B4', 'priority' => 'Tinggi', 'priority_tone' => 'danger', 'status' => 'Tunggu Material', 'age' => 9],
                ['code' => 'WO-2609-038', 'unit' => $units[1 % count($units)], 'asset' => 'Mesin #1 Mirrlees', 'task' => 'Perbaikan kebocoran radiator', 'priority' => 'Tinggi', 'priority_tone' => 'danger', 'status' => 'Dikerjakan', 'age' => 6],
                ['code' => 'WO-2609-035', 'unit' => $units[2 % count($units)], 'asset' => 'Genset Blackstart', 'task' => 'Kalibrasi governor & AVR', 'priority' => 'Sedang', 'priority_tone' => 'warning', 'status' => 'Dikerjakan', 'age' => 5],
                ['code' => 'WO-2609-029', 'unit' => $units[0], 'asset' => 'Trafo Step-Up 20 kV', 'task' => 'Pengujian tegangan tembus minyak', 'priority' => 'Sedang', 'priority_tone' => 'warning', 'status' => 'Terbuka', 'age' => 4],
                ['code' => 'WO-2609-024', 'unit' => $units[3 % count($units)], 'asset' => 'Mesin #2 Deutz', 'task' => 'Overhaul turbocharger', 'priority' => 'Rendah', 'priority_tone' => 'info', 'status' => 'Tunggu Material', 'age' => 12],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function k3(): array
    {
        $days = (int) $this->now->daysInMonth;
        $today = $this->now->day;
        $plan = 0;
        $real = 0;
        $points = [];
        for ($d = 1; $d <= $days; $d++) {
            $plan += (int) round(3 + 3 * $this->noise('k3-plan', $d));
            if ($d <= $today) {
                $real += (int) round(2 + 4 * $this->noise('k3-real', $d));
            }
            $points[] = ['day' => $d, 'plan' => $plan, 'real' => $d <= $today ? $real : null];
        }

        return [
            'kpis' => [
                $this->kpi('zero', 'Hari Tanpa Kecelakaan', 412, 'hari', 'number', 7.9, 'up', 'k3-k1'),
                $this->kpi('inspeksi', 'Inspeksi K3', 64, null, 'number', 10.3, 'up', 'k3-k2'),
                $this->kpi('unsafe', 'Unsafe Terbuka', 7, null, 'number', -22.2, 'down', 'k3-k3'),
                $this->kpi('cert', 'Sertifikat Expired', 3, null, 'number', -25.0, 'down', 'k3-k4'),
                $this->kpi('apar', 'APAR Siap Pakai', 96.2, '%', 'decimal', 1.5, 'up', 'k3-k5'),
            ],
            's_curve' => ['month_label' => $this->monthLabel(), 'points' => $points],
            'cert_status' => [
                ['label' => 'Berlaku', 'value' => 52],
                ['label' => 'Segera Habis', 'value' => 8],
                ['label' => 'Expired', 'value' => 3],
            ],
            'apar_status' => [
                ['label' => 'Berlaku', 'value' => 127],
                ['label' => 'Segera Habis', 'value' => 9],
                ['label' => 'Expired', 'value' => 2],
            ],
            'patrol_top' => [
                ['label' => 'Pos Gerbang Utama', 'value' => 186],
                ['label' => 'Ruang Mesin', 'value' => 174],
                ['label' => 'Tangki Timbun BBM', 'value' => 152],
                ['label' => 'Switchyard 20 kV', 'value' => 131],
                ['label' => 'Gudang Material', 'value' => 97],
                ['label' => 'Area Parkir', 'value' => 64],
            ],
            'compliance' => [
                'columns' => ['M1', 'M2', 'M3', 'M4'],
                'rows' => array_map(fn (string $name, int $i): array => [
                    'label' => $name,
                    'values' => array_map(fn (int $w): int => (int) round(68 + 32 * $this->noise("k3-c{$i}", $w)), range(1, 4)),
                ], $this->unitNames, array_keys($this->unitNames)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function logistik(): array
    {
        $months = $this->monthLabels();

        return [
            'kpis' => [
                $this->kpi('nilai', 'Nilai Persediaan', 3_847_200_000, null, 'rupiah', 2.7, 'up', 'log-k1'),
                $this->kpi('item', 'Item Material', 1248, null, 'number', 1.1, 'up', 'log-k2'),
                $this->kpi('kritis', 'Stok Kritis', 17, 'item', 'number', -15.0, 'down', 'log-k3'),
                $this->kpi('bbm', 'Penerimaan BBM', 3420.0, 'kL', 'decimal', 6.1, 'up', 'log-k4'),
                $this->kpi('pelumas', 'Penerimaan Pelumas', 18_400, 'L', 'number', -3.2, 'up', 'log-k5'),
            ],
            'tanks' => array_map(fn (string $name, int $i): array => [
                'label' => $name,
                'value' => (int) round(28 + 66 * $this->noise('log-tank', $i)),
                'capacity' => (int) round(200 + 400 * $this->noise('log-cap', $i)),
            ], $this->unitNames, array_keys($this->unitNames)),
            'fuel_flow' => [
                'labels' => $months,
                'series' => [
                    ['name' => 'Penerimaan (kL)', 'values' => array_map(fn (int $i): int => (int) round(2900 + 700 * $this->noise('log-in', $i)), range(0, 5))],
                    ['name' => 'Pemakaian (kL)', 'values' => array_map(fn (int $i): int => (int) round(2850 + 600 * $this->noise('log-out', $i)), range(0, 5))],
                ],
            ],
            'category' => [
                ['label' => 'Suku Cadang', 'value' => 612],
                ['label' => 'Consumable', 'value' => 284],
                ['label' => 'Pelumas', 'value' => 146],
                ['label' => 'APD', 'value' => 118],
                ['label' => 'Lainnya', 'value' => 88],
            ],
            'critical' => [
                ['code' => 'MAT-01842', 'name' => 'Filter oli MAN 18V (element)', 'unit' => $this->unitNames[0], 'stock' => 4, 'min' => 12, 'uom' => 'pcs'],
                ['code' => 'MAT-00977', 'name' => 'Gasket cylinder head', 'unit' => $this->unitNames[1 % count($this->unitNames)], 'stock' => 2, 'min' => 8, 'uom' => 'pcs'],
                ['code' => 'MAT-02210', 'name' => 'Fuel injector nozzle', 'unit' => $this->unitNames[0], 'stock' => 6, 'min' => 16, 'uom' => 'pcs'],
                ['code' => 'MAT-01105', 'name' => 'V-belt fan radiator', 'unit' => $this->unitNames[2 % count($this->unitNames)], 'stock' => 3, 'min' => 6, 'uom' => 'pcs'],
                ['code' => 'MAT-00431', 'name' => 'Coolant concentrate', 'unit' => $this->unitNames[3 % count($this->unitNames)], 'stock' => 40, 'min' => 100, 'uom' => 'L'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pdm(): array
    {
        $months = $this->monthLabels();
        $weeks = array_map(fn (int $w): string => 'M'.$w, range(1, 12));
        $engines = ['Mesin #1', 'Mesin #2', 'Mesin #3'];

        return [
            'kpis' => [
                $this->kpi('titik', 'Titik Ukur', 342, null, 'number', 4.3, 'up', 'pdm-k1'),
                $this->kpi('realisasi', 'Realisasi Prediktif', 91.8, '%', 'decimal', 2.6, 'up', 'pdm-k2'),
                $this->kpi('anomali', 'Anomali Terdeteksi', 9, null, 'number', -18.2, 'down', 'pdm-k3'),
                $this->kpi('sample', 'Sample Oli Diuji', 26, null, 'number', 8.0, 'up', 'pdm-k4'),
                $this->kpi('kritis', 'Aset Status Alarm', 2, 'aset', 'number', -33.3, 'down', 'pdm-k5'),
            ],
            'realisasi' => [
                'labels' => $months,
                'series' => [
                    ['name' => 'Rencana', 'values' => array_map(fn (int $i): int => (int) round(52 + 10 * $this->noise('pdm-pl', $i)), range(0, 5))],
                    ['name' => 'Realisasi', 'values' => array_map(fn (int $i): int => (int) round(44 + 14 * $this->noise('pdm-re', $i)), range(0, 5))],
                ],
            ],
            'condition' => [
                ['label' => 'Normal', 'value' => 41],
                ['label' => 'Waspada', 'value' => 6],
                ['label' => 'Alarm', 'value' => 2],
            ],
            'vibration' => [
                'labels' => $weeks,
                'threshold' => 7.1,
                'series' => array_map(fn (string $engine, int $e): array => [
                    'name' => $engine,
                    'values' => array_map(fn (int $w): float => round(2.4 + $e * 0.9 + 1.4 * $this->noise("pdm-v{$e}", $w) + ($e === 2 ? $w * 0.22 : 0), 2), range(1, 12)),
                ], $engines, array_keys($engines)),
            ],
            'assets' => array_map(fn (string $name, int $i): array => [
                'asset' => $engines[$i % 3].' — '.$name,
                'vibration' => round(2.1 + 5.8 * $this->noise('pdm-av', $i), 2),
                'temperature' => round(62 + 22 * $this->noise('pdm-at', $i), 1),
                'oil' => ['Baik', 'Baik', 'Perlu Ganti', 'Baik', 'Kontaminasi'][$i % 5],
                ...$this->pickStatus($i, [['Normal', 'success'], ['Normal', 'success'], ['Waspada', 'warning'], ['Normal', 'success'], ['Alarm', 'danger']]),
            ], $this->unitNames, array_keys($this->unitNames)),
        ];
    }

    /**
     * Health score per visible module, for the executive overview rings.
     *
     * @param  array<string, bool>  $scope
     * @return list<array{key: string, label: string, value: float, caption: string}>
     */
    public function moduleHealth(array $scope): array
    {
        $all = [
            'operasi' => ['label' => 'Operasi', 'value' => 92.4, 'caption' => 'EAF bulan berjalan'],
            'har' => ['label' => 'Pemeliharaan', 'value' => 78.6, 'caption' => 'WO selesai'],
            'k3' => ['label' => 'K3 & Keamanan', 'value' => 88.1, 'caption' => 'Realisasi kegiatan'],
            'logistik' => ['label' => 'Logistik', 'value' => 84.0, 'caption' => 'Ketersediaan stok'],
            'pdm' => ['label' => 'PdM', 'value' => 91.8, 'caption' => 'Realisasi prediktif'],
        ];

        $out = [];
        foreach ($all as $key => $item) {
            if ($scope[$key] ?? false) {
                $out[] = ['key' => $key, ...$item];
            }
        }

        return $out;
    }

    /**
     * Entries logged this month per module, for the activity bar list.
     *
     * @param  array<string, bool>  $scope
     * @return list<array{label: string, value: int}>
     */
    public function moduleActivity(array $scope): array
    {
        $all = [
            'operasi' => [['Input Harian Operasi', 684], ['Logsheet Operator', 552]],
            'har' => [['Work Order', 83], ['Service Request', 41]],
            'k3' => [['Inspeksi K3', 64], ['Scan Patroli', 804]],
            'logistik' => [['Transaksi Material', 237]],
            'pdm' => [['Pengukuran PdM', 318]],
        ];

        $out = [];
        foreach ($all as $key => $items) {
            if ($scope[$key] ?? false) {
                foreach ($items as [$label, $value]) {
                    $out[] = ['label' => $label, 'value' => $value];
                }
            }
        }

        usort($out, fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return $out;
    }

    /**
     * @return array{key: string, label: string, value: float|int, unit: string|null, format: string, delta: float, good: string, spark: list<float>}
     */
    private function kpi(string $key, string $label, float|int $value, ?string $unit, string $format, float $delta, string $good, string $seed): array
    {
        $spark = array_map(fn (int $i): float => round(40 + 40 * $this->noise($seed, $i) + ($delta > 0 ? $i * 2.5 : -$i * 2.5), 1), range(1, 12));

        return ['key' => $key, 'label' => $label, 'value' => $value, 'unit' => $unit, 'format' => $format, 'delta' => $delta, 'good' => $good, 'spark' => $spark];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $options
     * @return array{status: string, status_tone: string}
     */
    private function pickStatus(int $index, array $options): array
    {
        [$status, $tone] = $options[$index % count($options)];

        return ['status' => $status, 'status_tone' => $tone];
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function monthly(string $seed, float $base, float $spread): array
    {
        return array_map(fn (string $label, int $i): array => [
            'label' => $label,
            'value' => round($base + $spread * $this->noise($seed, $i), 1),
        ], $this->monthLabels(), range(0, 5));
    }

    /**
     * The last six month labels ending with the current month, e.g. "Apr 26".
     *
     * @return list<string>
     */
    private function monthLabels(): array
    {
        $cursor = $this->now->copy()->startOfMonth()->subMonths(5);
        $labels = [];
        for ($i = 0; $i < 6; $i++) {
            $labels[] = mb_substr(Indonesian::monthName($cursor->month), 0, 3).' '.substr((string) $cursor->year, 2);
            $cursor->addMonth();
        }

        return $labels;
    }

    private function monthLabel(): string
    {
        return Indonesian::monthName($this->now->month).' '.$this->now->year;
    }

    /** A stable pseudo-random value in [0, 1) for the given key and index. */
    private function noise(string $key, int $index): float
    {
        return (crc32($key.':'.$index) % 1000) / 1000;
    }
}
