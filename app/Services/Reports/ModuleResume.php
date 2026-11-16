<?php

namespace App\Services\Reports;

use App\Enums\JamMesinJenis;
use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Models\LubricantType;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\DashboardSupport;
use App\Services\Dashboard\HarDashboard;
use App\Services\Dashboard\K3Dashboard;
use App\Services\Dashboard\LogistikDashboard;
use App\Services\Dashboard\OperasiDashboard;
use App\Services\Dashboard\PdmDashboard;
use App\Services\Monitoring\InputCatalog;
use App\Services\Monitoring\InputCompleteness;
use App\Services\Operasi\JamMesinSheet;
use App\Services\Operasi\KinerjaTermalSheet;
use App\Services\Operasi\UnitFuelTypes;
use App\Support\Indonesian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Resume Bulanan per modul (PRD.md §8.11): one unit, one month, as KPI tiles,
 * executive metadata, direction analysis, 3D charts and detail tables — the
 * same real figures as the dashboard ({@see OperasiDashboard} and the other
 * module services), the input completeness of the module's menus and the status
 * of its reports. One payload feeds the preview page, the PDF and the Excel
 * export, so all three always show the same numbers.
 *
 * @phpstan-type Section array{title: string, columns: list<string>, rows: list<list<string>>, footer?: list<string>, note?: string}
 */
class ModuleResume
{
    /** @var array<string, array{0: string, 1: list<PermissionName>, 2: list<string>, 3: list<ReportModule>}> module => [label, view permissions, input groups, reports] */
    public const MODULES = [
        'operasi' => ['Operasi Pembangkit', [PermissionName::OperasiLaporanView, PermissionName::OperasiPengusahaanView], ['operasi', 'operasi_pengusahaan', 'operator'], [ReportModule::Operasi, ReportModule::OperasiPengusahaan]],
        'pemeliharaan' => ['Pemeliharaan Pembangkit', [PermissionName::HarLaporanView, PermissionName::HarPengusahaanView], ['har', 'har_pengusahaan'], [ReportModule::Har, ReportModule::HarPengusahaan]],
        'k3' => ['K3 & Keamanan', [PermissionName::K3LaporanView, PermissionName::K3PengusahaanView], ['k3', 'k3_pengusahaan'], [ReportModule::K3, ReportModule::K3Pengusahaan]],
        'logistik' => ['Logistik & Gudang', [PermissionName::LogistikLaporanView], ['logistik'], [ReportModule::Logistik]],
        'pdm' => ['PdM & Maturity Level', [PermissionName::PdmLaporanView], ['pdm'], [ReportModule::Pdm]],
    ];

    public function __construct(
        private readonly OperasiDashboard $operasi,
        private readonly HarDashboard $har,
        private readonly K3Dashboard $k3,
        private readonly LogistikDashboard $logistik,
        private readonly PdmDashboard $pdm,
        private readonly InputCompleteness $completeness,
        private readonly ReportWorkflowService $workflows,
        private readonly UnitFuelTypes $fuelTypes,
        private readonly JamMesinSheet $jamMesinSheet,
        private readonly KinerjaTermalSheet $kinerjaTermalSheet,
        private readonly Chart3d $chart3d,
    ) {}

    /** Whether the user may open the resume of the module (any of its laporan views). */
    public static function allows(User $user, string $module): bool
    {
        return isset(self::MODULES[$module])
            && collect(self::MODULES[$module][1])->contains(fn (PermissionName $permission): bool => $user->hasPermissionTo($permission));
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $module, Unit $unit, int $month, int $year): array
    {
        [$label, , $groups] = self::MODULES[$module];
        $unit->loadMissing('serviceUnit:id,name');
        $units = new Collection([$unit]);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        // The current month runs to today; a past (or future) month is read whole.
        $now = $start->isSameMonth(Carbon::now()) ? Carbon::now() : $start->copy()->endOfMonth();

        $data = match ($module) {
            'operasi' => $this->operasi->build($units, $now, $this->fuels($unit), $this->lubricants($unit)),
            'pemeliharaan' => $this->har->build($units, $now),
            'k3' => $this->k3->build($units, $now),
            'logistik' => $this->logistik->build($units, $now),
            'pdm' => $this->pdm->build($units, $now),
        };

        if ($module === 'operasi') {
            $data['machines'] = $this->operasiMachines($unit, $month, $year);
        }

        $sections = match ($module) {
            'operasi' => $this->operasiSections($data),
            'pemeliharaan' => $this->harSections($data),
            'k3' => $this->k3Sections($data),
            'logistik' => $this->logistikSections($data),
            'pdm' => $this->pdmSections($data),
        };

        $reportStatusSection = $this->reportStatus($module, $unit, $month, $year);
        $completenessSection = $this->inputCompleteness($units, $month, $year, $groups);
        $machineCount = $unit->machines()->where('is_active', true)->count();
        $periodLabel = Indonesian::monthName($month).' '.$year;

        $reportSummary = implode(' · ', array_map(
            fn (array $r): string => "{$r[0]}: {$r[1]}",
            $reportStatusSection['rows'],
        ));
        $completenessFooter = $completenessSection['footer'] ?? [];
        $completenessSummary = ($completenessFooter[3] ?? '0 %').' ('.($completenessFooter[1] ?? '0').'/'.($completenessFooter[2] ?? '0').' target input)';

        return [
            'module' => $module,
            'title' => "Resume Bulanan {$label}",
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'service_unit' => $unit->serviceUnit?->name,
                'type' => $unit->type?->value,
                'contract' => $unit->contract?->value,
                'capacity_mw' => round((float) ($unit->installed_capacity_mw ?? 0), 2),
                'status' => $unit->status?->label() ?? '—',
                'machine_count' => $machineCount,
            ],
            'period' => ['month' => $month, 'year' => $year, 'label' => $periodLabel],
            'meta' => [
                ['label' => 'Unit Pembangkit', 'value' => $unit->name.($unit->code ? " ({$unit->code})" : '')],
                ['label' => 'Unit Layanan (UL)', 'value' => $unit->serviceUnit?->name ?? 'UP Kendari (Langsung)'],
                ['label' => 'Spesifikasi & Kapasitas', 'value' => trim(($unit->type?->value ?? '—').' · '.($unit->contract?->value ?? '—').' · '.$this->format($unit->installed_capacity_mw, 'decimal').' MW')],
                ['label' => 'Status Unit & Mesin', 'value' => ($unit->status?->label() ?? '—')." · {$machineCount} Mesin Aktif"],
                ['label' => 'Periode & Modul', 'value' => "{$periodLabel} · {$label}"],
                ['label' => 'Status Pengesahan', 'value' => $reportSummary ?: '—'],
                ['label' => 'Kelengkapan Input', 'value' => $completenessSummary],
                ['label' => 'Waktu Pembuatan Resume', 'value' => Carbon::now()->timezone(config('app.timezone'))->format('d/m/Y H:i').' WITA'],
            ],
            'kpis' => array_map(function (array $kpi): array {
                $unitSuffix = ($kpi['unit'] ?? '') !== '' ? ' '.$kpi['unit'] : '';
                $sparkValues = array_values(array_map('floatval', $kpi['spark'] ?? []));
                $nonZero = array_values(array_filter($sparkValues, fn (float $v): bool => $v > 0));

                return [
                    'label' => $kpi['label'],
                    'value' => $kpi['value'] === null ? '—' : trim($this->format($kpi['value'], $kpi['format']).$unitSuffix),
                    'previous' => ($kpi['previous'] ?? null) === null ? '—' : trim($this->format($kpi['previous'], $kpi['format']).$unitSuffix),
                    'good' => $kpi['good'],
                    'target_label' => $kpi['good'] === 'up' ? 'Makin tinggi makin baik' : 'Makin rendah makin baik',
                    'avg' => $nonZero === [] ? '—' : trim($this->format(array_sum($nonZero) / count($nonZero), $kpi['format']).$unitSuffix),
                    'max' => $sparkValues === [] ? '—' : trim($this->format(max($sparkValues), $kpi['format']).$unitSuffix),
                    ...$this->direction($kpi),
                    'spark' => $sparkValues,
                ];
            }, $data['kpis']),
            'insights' => $this->insights($module, $data),
            // Every chart is drawn as a 3D PNG (Chart3d): the page, the PDF and the Excel show the same image.
            'charts' => array_map(
                fn (array $chart): array => [...$chart, 'image' => $this->image($chart)],
                $this->charts($module, $data, array_column(DashboardSupport::months($now), 'label')),
            ),
            'sections' => [
                ...$sections,
                $reportStatusSection,
                $completenessSection,
            ],
        ];
    }

    /**
     * Per-machine operational breakdown for the unit in the selected month:
     * capacity, jam pembangkit (OPS, Standby, PO, MO, Gangguan), kWh produksi,
     * BBM liter and thermal performance indices (EAF, EFOR, CF).
     *
     * @return list<array<string, mixed>>
     */
    private function operasiMachines(Unit $unit, int $month, int $year): array
    {
        $termal = $this->kinerjaTermalSheet->build($unit, $month, $year);
        $machines = $this->jamMesinSheet->machines($unit);
        $hoursByJenis = [];
        foreach (JamMesinJenis::cases() as $jenis) {
            $hoursByJenis[$jenis->value] = $this->jamMesinSheet->summarize(
                $this->jamMesinSheet->readings($unit, $jenis, $month, $year, $machines),
                $machines,
            )['totals_by_machine'];
        }
        $dayaMampu = collect($termal['rows'])->mapWithKeys(fn (array $r): array => [
            $r['machine']['id'] => (float) ($r['machine']['capacity_kw'] ?? 0),
        ]);

        return array_map(function (array $row) use ($hoursByJenis, $dayaMampu): array {
            $id = (int) $row['machine']['id'];
            $src = $row['source'];
            $val = $row['values'];

            return [
                'name' => (string) $row['machine']['name'],
                'kondisi' => (string) $row['kondisi'],
                'operating' => (bool) $row['operating'] && (float) $src['jam_operasi'] > 0,
                'daya_terpasang' => round((float) $src['daya_terpasang'], 2),
                'daya_mampu' => round((float) ($row['machine']['daya_mampu'] ?? $dayaMampu[$id] ?? $src['daya_terpasang']), 2),
                'jam_operasi' => round((float) ($hoursByJenis[JamMesinJenis::Operasi->value][$id] ?? $src['jam_operasi']), 2),
                'jam_standby' => round((float) ($hoursByJenis[JamMesinJenis::Standby->value][$id] ?? 0), 2),
                'jam_po' => round((float) ($hoursByJenis[JamMesinJenis::Pemeliharaan->value][$id] ?? $src['jam_har']), 2),
                'jam_mo' => round((float) ($hoursByJenis[JamMesinJenis::MaintenanceOutage->value][$id] ?? 0), 2),
                'jam_gangguan' => round((float) ($hoursByJenis[JamMesinJenis::Gangguan->value][$id] ?? $src['jam_gangguan']), 2),
                'kwh' => round((float) $src['kwh'], 2),
                'bbm_liter' => round((float) $src['bbm_liter'], 2),
                'eaf' => round((float) ($val['eaf'] ?? 0), 2),
                'efor' => round((float) ($val['efor'] ?? 0), 2),
                'cf' => round((float) ($val['cf'] ?? 0), 2),
            ];
        }, $termal['rows']);
    }

    /**
     * The charts of the resume: every model the data suits — bar (grouped /
     * stacked, with a line), line (with a threshold), donut, gauge, horizontal
     * bars, level bars, S-curve and heatmap. Every module has an even number of
     * charts so the 2-column grid in Web, PDF and Excel is always symmetrical.
     *
     * @param  array<string, mixed>  $d
     * @param  list<string>  $months  the six month labels ending with the period
     * @return list<array<string, mixed>>
     */
    private function charts(string $module, array $d, array $months): array
    {
        $kpi = fn (string $key): array => collect($d['kpis'])->firstWhere('key', $key) ?? ['spark' => [], 'label' => $key];
        $sparkLine = function (string $key, string $title, string $subtitle, array $labels) use ($kpi): array {
            $item = $kpi($key);

            return ['title' => $title, 'subtitle' => $subtitle, 'type' => 'line', 'labels' => array_slice($labels, -count($item['spark'])), 'series' => [['name' => $item['label'], 'values' => $item['spark']]]];
        };
        $donut = fn (string $title, string $subtitle, array $data): array => ['title' => $title, 'subtitle' => $subtitle, 'type' => 'donut', 'data' => array_values($data)];
        $cumulative = fn (string $title, string $subtitle, array $chart, ?array $only = null): array => [
            'title' => $title, 'subtitle' => $subtitle, 'type' => 'line', 'labels' => $chart['labels'],
            'series' => array_values(array_map(
                fn (array $serie): array => ['name' => 'Kumulatif '.$serie['name'], 'values' => $this->cumulative($serie['values'])],
                $only === null ? $chart['series'] : array_intersect_key($chart['series'], array_flip($only)),
            )),
        ];

        $noOperatingHours = $module === 'operasi' && collect($d['units'] ?? [])->every(fn (array $u): bool => $u['eaf'] === null);
        $machines = $d['machines'] ?? [];
        $machineLabels = $machines !== [] ? array_column($machines, 'name') : ['Belum ada mesin'];

        return match ($module) {
            'operasi' => [
                ['title' => 'kWh Netto, Pemakaian Sendiri & Beban Tertinggi', 'subtitle' => 'Harian — batang bertumpuk (MWh) & garis beban (MW)', 'type' => 'stacked', 'line_last' => true, ...$d['daily']],
                ['title' => 'Indikator Kinerja (EAF, EFOR, CF)', 'subtitle' => 'Gauge terhadap target (EAF ≥ 90%, EFOR ≤ 5%, CF ≥ 60%)', 'type' => 'gauge', 'items' => array_map(fn (array $g): array => [...$g, 'no_data' => $noOperatingHours], $d['gauges'])],
                $donut('Komposisi Jam Pembangkit', 'Jam seluruh mesin bulan ini', $d['jam_mesin']),
                $cumulative('Akumulasi Produksi kWh Netto & Pemakaian Sendiri', 'Kumulatif harian bulan ini (MWh)', $d['daily'], [0, 1]),
                $sparkLine('ps', 'Tren Pemakaian Sendiri Harian', 'Persentase terhadap kWh produksi (%)', $d['daily']['labels']),
                $sparkLine('beban', 'Tren Beban Tertinggi Harian', 'MW per hari', $d['daily']['labels']),
                [
                    'title' => 'Jam Pembangkit per Mesin',
                    'subtitle' => 'Operasi, Standby, PO, MO & Gangguan per mesin bulan ini (jam)',
                    'type' => 'stacked',
                    'labels' => $machineLabels,
                    'series' => [
                        ['name' => 'Operasi', 'values' => $machines !== [] ? array_column($machines, 'jam_operasi') : [0]],
                        ['name' => 'Standby', 'values' => $machines !== [] ? array_column($machines, 'jam_standby') : [0]],
                        ['name' => 'Pemeliharaan (PO)', 'values' => $machines !== [] ? array_column($machines, 'jam_po') : [0]],
                        ['name' => 'MO', 'values' => $machines !== [] ? array_column($machines, 'jam_mo') : [0]],
                        ['name' => 'Gangguan', 'values' => $machines !== [] ? array_column($machines, 'jam_gangguan') : [0]],
                    ],
                ],
                [
                    'title' => 'Kapasitas Daya & Produksi kWh per Mesin',
                    'subtitle' => 'Daya Terpasang (kW) & garis Produksi kWh (MWh) per mesin',
                    'type' => 'bar',
                    'line_last' => true,
                    'labels' => $machineLabels,
                    'series' => [
                        ['name' => 'Daya Terpasang (kW)', 'values' => $machines !== [] ? array_column($machines, 'daya_terpasang') : [0]],
                        ['name' => 'Pemakaian BBM (kL)', 'values' => $machines !== [] ? array_map(fn (array $m): float => round($m['bbm_liter'] / 1000, 2), $machines) : [0]],
                        ['name' => 'Produksi (MWh)', 'values' => $machines !== [] ? array_map(fn (array $m): float => round($m['kwh'] / 1000, 2), $machines) : [0]],
                    ],
                ],
                ['title' => 'Pemakaian BBM per Jenis', 'subtitle' => '6 bulan terakhir — batang bertumpuk (kL)', 'type' => 'stacked', ...$d['fuel_monthly']],
                $cumulative('Akumulasi Pemakaian BBM', 'Kumulatif 6 bulan terakhir per jenis (kL)', $d['fuel_monthly']),
                ['title' => 'Persediaan Bahan Bakar', 'subtitle' => 'Per jenis BBM — batang berkelompok (liter)', 'type' => 'bar', 'labels' => array_column($d['fuels'], 'code'), 'series' => array_map(fn (string $field, string $name): array => ['name' => $name, 'values' => array_column($d['fuels'], $field)], ['awal', 'penerimaan', 'pemakaian', 'sisa'], ['Awal', 'Penerimaan', 'Pemakaian', 'Sisa'])],
                ['title' => 'Pelumas Tambah & Ganti', 'subtitle' => 'Per jenis pelumas (liter)', 'type' => 'hbar', 'data' => array_map(fn (array $p): array => ['label' => $p['label'], 'value' => $p['tambah'] + $p['ganti']], $d['pelumas'])],
            ],
            'pemeliharaan' => [
                ['title' => 'Tren Work Order & Service Request', 'subtitle' => '6 bulan terakhir — batang berkelompok', 'type' => 'bar', ...$d['trend']],
                $cumulative('Akumulasi Work Order & Service Request', 'Kumulatif 6 bulan terakhir', $d['trend']),
                ['title' => 'Biaya Pemeliharaan', 'subtitle' => 'Jasa vs material — batang bertumpuk (Rp)', 'type' => 'stacked', ...$d['cost']],
                $cumulative('Akumulasi Biaya Pemeliharaan', 'Kumulatif 6 bulan terakhir (Rp)', $d['cost']),
                $donut('Komposisi WO — Jenis', 'Bulan ini', $d['by_type']),
                $donut('Komposisi WO — Status', 'Bulan ini', $d['by_status']),
                $sparkLine('wo_closed', 'Rasio WO Selesai', '6 bulan terakhir (%)', $months),
                $sparkLine('sr_open', 'Tren SR Terbuka', '6 bulan terakhir', $months),
                ['title' => 'Backlog WO per Umur', 'subtitle' => 'Hari sejak dijadwalkan (WO belum selesai)', 'type' => 'hbar', 'data' => array_map(fn (array $w): array => ['label' => $w['code'].' · '.$w['asset'], 'value' => $w['age']], $d['backlog'])],
                $donut('Proporsi Biaya Jasa vs Material', 'Akumulasi 6 bulan terakhir (Rp)', [
                    ['label' => 'Biaya Jasa', 'value' => round(array_sum($d['cost']['series'][0]['values'] ?? []), 0)],
                    ['label' => 'Biaya Material', 'value' => round(array_sum($d['cost']['series'][1]['values'] ?? []), 0)],
                ]),
            ],
            'k3' => [
                ['title' => 'Kurva S Kegiatan K3', 'subtitle' => 'Rencana vs realisasi kumulatif (Time Frame)', 'type' => 'scurve', 'points' => $d['s_curve']['points']],
                ['title' => 'Kepatuhan Kegiatan K3 per Minggu', 'subtitle' => 'Heatmap realisasi ÷ rencana (%)', 'type' => 'heatmap', 'columns' => $d['compliance']['columns'], 'rows' => $d['compliance']['rows']],
                $donut('Status Sertifikat Alat', 'Masa berlaku uji ulang', $d['cert_status']),
                $donut('Status APAR / APAB', 'Masa berlaku tabung', $d['apar_status']),
                ['title' => 'Patroli Security per Lokasi', 'subtitle' => 'Jumlah scan bulan ini', 'type' => 'hbar', 'data' => $d['patrol_top']],
                $sparkLine('inspeksi', 'Tren Inspeksi K3', '6 bulan terakhir', $months),
                ['title' => 'Perbandingan Inspeksi K3 vs Unsafe Terbuka', 'subtitle' => '6 bulan terakhir — batang berkelompok', 'type' => 'bar', 'labels' => $months, 'series' => [
                    ['name' => 'Inspeksi K3', 'values' => $kpi('inspeksi')['spark']],
                    ['name' => 'Unsafe terbuka', 'values' => $kpi('unsafe')['spark']],
                ]],
                $cumulative('Akumulasi Inspeksi & Unsafe Terbuka', 'Kumulatif 6 bulan terakhir', ['labels' => $months, 'series' => [
                    ['name' => 'Inspeksi K3', 'values' => $kpi('inspeksi')['spark']],
                    ['name' => 'Unsafe terbuka', 'values' => $kpi('unsafe')['spark']],
                ]]),
            ],
            'logistik' => [
                ['title' => 'Penerimaan vs Pemakaian BBM', 'subtitle' => '6 bulan terakhir — batang berkelompok (kL)', 'type' => 'bar', ...$d['fuel_flow']],
                $cumulative('Akumulasi Penerimaan vs Pemakaian BBM', 'Kumulatif 6 bulan terakhir (kL) — selisih garis = cadangan bertambah / berkurang', $d['fuel_flow']),
                ['title' => 'Level Tangki Timbun BBM', 'subtitle' => 'Isi terhadap kapasitas (%)', 'type' => 'level', 'data' => $d['tanks']],
                $donut('Status Stok Tools & Material', 'Laporan Kondisi Stok terakhir', $d['category']),
                ['title' => 'Material Stok Kritis', 'subtitle' => 'Kekurangan terhadap ROP', 'type' => 'hbar', 'data' => array_map(fn (array $m): array => ['label' => $m['name'], 'value' => max(0, $m['min'] - $m['stock'])], $d['critical'])],
                [
                    'title' => 'Stok Akhir vs ROP Material Kritis',
                    'subtitle' => 'Perbandingan stok akhir terhadap batas Reorder Point (ROP)',
                    'type' => 'bar',
                    'labels' => $d['critical'] !== [] ? array_map(fn (array $m): string => mb_strimwidth($m['name'], 0, 16, '…'), $d['critical']) : ['Belum ada stok kritis'],
                    'series' => [
                        ['name' => 'Stok Akhir', 'values' => $d['critical'] !== [] ? array_column($d['critical'], 'stock') : [0]],
                        ['name' => 'Batas ROP', 'values' => $d['critical'] !== [] ? array_column($d['critical'], 'min') : [0]],
                    ],
                ],
                $sparkLine('pelumas', 'Tren Penerimaan Pelumas', '6 bulan terakhir (liter)', $months),
                $cumulative('Akumulasi Penerimaan Pelumas', 'Kumulatif 6 bulan terakhir (liter)', [
                    'labels' => $months,
                    'series' => [['name' => 'Penerimaan Pelumas (L)', 'values' => $kpi('pelumas')['spark']]],
                ]),
            ],
            'pdm' => [
                ['title' => 'Rencana vs Realisasi Prediktif', 'subtitle' => '6 bulan terakhir — batang berkelompok (hari)', 'type' => 'bar', ...$d['realisasi']],
                $cumulative('Akumulasi Rencana vs Realisasi Prediktif', 'Kumulatif 6 bulan terakhir (hari)', $d['realisasi']),
                ['title' => 'Tren Vibrasi Mesin', 'subtitle' => 'Nilai tertinggi per bulan (mm/s) dengan batas alarm ISO 10816', 'type' => 'line', 'labels' => $d['vibration']['labels'], 'series' => $d['vibration']['series'], 'threshold' => $d['vibration']['threshold'], 'threshold_label' => 'Batas alarm'],
                $donut('Kondisi Aset', 'Uji vibrasi terakhir per mesin', $d['condition']),
                $sparkLine('realisasi', 'Capaian Realisasi Prediktif', '6 bulan terakhir (%)', $d['realisasi']['labels']),
                ['title' => 'Vibrasi Maksimum per Aset', 'subtitle' => 'Uji terakhir (mm/s)', 'type' => 'hbar', 'data' => array_map(fn (array $a): array => ['label' => $a['asset'], 'value' => (float) ($a['vibration'] ?? 0)], $d['assets'])],
                [
                    'title' => 'Tren Titik Ukur & Sample Oli Diuji',
                    'subtitle' => '6 bulan terakhir — batang berkelompok',
                    'type' => 'bar',
                    'labels' => $months,
                    'series' => [
                        ['name' => 'Titik Ukur Vibrasi', 'values' => $kpi('titik')['spark']],
                        ['name' => 'Sample Oli Diuji', 'values' => $kpi('sample')['spark']],
                    ],
                ],
                $sparkLine('anomali', 'Tren Anomali Vibrasi Terdeteksi', '6 bulan terakhir (pengujian > batas waspada)', $months),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<Section>
     */
    private function operasiSections(array $d): array
    {
        $daily = $d['daily'];
        $series = array_column($daily['series'], 'values');
        $cumNett = $this->cumulative($series[0] ?? []);
        $cumPs = $this->cumulative($series[1] ?? []);
        $machines = $d['machines'] ?? [];
        $totalJam = array_sum(array_column($d['jam_mesin'], 'value'));

        $machineSection = $this->section(
            'Rincian Kinerja & Jam Operasi per Mesin',
            ['Mesin', 'Kondisi', 'Daya Terpasang (kW)', 'Jam Operasi', 'Jam Standby', 'Jam HAR (PO/MO)', 'Jam Gangguan', 'Produksi (kWh)', 'Pemakaian BBM (L)', 'EAF (%)', 'EFOR (%)', 'CF (%)'],
            array_map(fn (array $m): array => [
                $m['name'],
                $m['kondisi'],
                $this->format($m['daya_terpasang'], 'decimal'),
                $this->format($m['jam_operasi'], 'decimal'),
                $this->format($m['jam_standby'], 'decimal'),
                $this->format($m['jam_po'] + $m['jam_mo'], 'decimal'),
                $this->format($m['jam_gangguan'], 'decimal'),
                $this->format($m['kwh'], 'decimal'),
                $this->format($m['bbm_liter'], 'decimal'),
                $m['operating'] ? $this->format($m['eaf'], 'decimal') : '—',
                $m['operating'] ? $this->format($m['efor'], 'decimal') : '—',
                $m['operating'] ? $this->format($m['cf'], 'decimal') : '—',
            ], $machines),
            footer: $machines === [] ? null : [
                'TOTAL / RATA-RATA',
                count(array_filter($machines, fn (array $m): bool => $m['operating'])).'/'.count($machines).' Beroperasi',
                $this->format(array_sum(array_column($machines, 'daya_terpasang')), 'decimal'),
                $this->format(array_sum(array_column($machines, 'jam_operasi')), 'decimal'),
                $this->format(array_sum(array_column($machines, 'jam_standby')), 'decimal'),
                $this->format(array_sum(array_column($machines, 'jam_po')) + array_sum(array_column($machines, 'jam_mo')), 'decimal'),
                $this->format(array_sum(array_column($machines, 'jam_gangguan')), 'decimal'),
                $this->format(array_sum(array_column($machines, 'kwh')), 'decimal'),
                $this->format(array_sum(array_column($machines, 'bbm_liter')), 'decimal'),
                $this->format($d['gauges'][0]['value'] ?? 0, 'decimal'),
                $this->format($d['gauges'][1]['value'] ?? 0, 'decimal'),
                $this->format($d['gauges'][2]['value'] ?? 0, 'decimal'),
            ],
        );

        return [
            $this->section('Indikator Kinerja Pembangkit', ['Indikator', 'Realisasi (%)', 'Target (%)', 'Keterangan'], array_map(fn (array $g): array => [
                $g['label'], $this->format($g['value'], 'decimal'), ($g['good'] === 'up' ? '≥ ' : '≤ ').$this->format($g['target'], 'decimal'),
                collect($d['units'])->every(fn (array $u): bool => $u['eaf'] === null) ? 'Belum ada jam operasi' : (($g['good'] === 'up' ? $g['value'] >= $g['target'] : $g['value'] <= $g['target']) ? 'Tercapai' : 'Belum tercapai'),
            ], $d['gauges'])),
            $this->section('Kinerja Unit', ['Unit', 'Daya Mampu (MW)', 'Beban Tertinggi (MW)', 'kWh Netto (MWh)', 'SFC Netto (L/kWh)', 'Tara Kalor (kCal/kWh)', 'EAF (%)', 'Status Unit'], array_map(fn (array $u): array => [
                $u['unit'], $this->format($u['daya_mampu'], 'decimal'), $this->format($u['beban_puncak'], 'decimal'), $this->format($u['produksi'], 'decimal'),
                $this->format($u['sfc'], 'decimal3'), $this->format($u['tara_kalor'], 'number'), $u['eaf'] === null ? '—' : $this->format($u['eaf'], 'decimal'), $u['status'],
            ], $d['units'])),
            $machineSection,
            $this->section(
                'Produksi Harian & Akumulasi',
                ['Tanggal', 'kWh Netto (MWh)', 'Kumulatif kWh Netto (MWh)', 'Pemakaian Sendiri (MWh)', 'Kumulatif PS (MWh)', 'Beban Tertinggi (MW)'],
                array_map(fn (string $day, int $i): array => [
                    $day, $this->format($series[0][$i] ?? 0, 'decimal'), $this->format($cumNett[$i] ?? 0, 'decimal'), $this->format($series[1][$i] ?? 0, 'decimal'), $this->format($cumPs[$i] ?? 0, 'decimal'), $this->format($series[2][$i] ?? 0, 'decimal'),
                ], $daily['labels'], array_keys($daily['labels'])),
                footer: [
                    'TOTAL / PUNCAK',
                    $this->format(array_sum($series[0] ?? []), 'decimal'),
                    $this->format(end($cumNett) ?: 0, 'decimal'),
                    $this->format(array_sum($series[1] ?? []), 'decimal'),
                    $this->format(end($cumPs) ?: 0, 'decimal'),
                    $this->format(max([0, ...($series[2] ?? [0])]), 'decimal'),
                ],
            ),
            $this->section(
                'Jam Pembangkit (seluruh mesin)',
                ['Kategori', 'Jam', 'Proporsi (%)'],
                array_map(fn (array $j): array => [
                    $j['label'],
                    $this->format($j['value'], 'decimal'),
                    $totalJam > 0 ? $this->format($j['value'] / $totalJam * 100, 'decimal').' %' : '0,00 %',
                ], $d['jam_mesin']),
                footer: ['TOTAL JAM PEMBANGKIT', $this->format($totalJam, 'decimal'), $totalJam > 0 ? '100,00 %' : '0,00 %'],
            ),
            $this->section(
                'Persediaan Bahan Bakar (liter)',
                ['Jenis BBM', 'Awal', 'Penerimaan', 'Pemakaian', 'Pengiriman', 'Sisa'],
                array_map(fn (array $f): array => [
                    "{$f['name']} ({$f['code']})", $this->format($f['awal']), $this->format($f['penerimaan']), $this->format($f['pemakaian']), $this->format($f['pengiriman']), $this->format($f['sisa']),
                ], $d['fuels']),
                footer: $d['fuels'] === [] ? null : [
                    'TOTAL PERSEDIAAN BBM',
                    $this->format(array_sum(array_column($d['fuels'], 'awal'))),
                    $this->format(array_sum(array_column($d['fuels'], 'penerimaan'))),
                    $this->format(array_sum(array_column($d['fuels'], 'pemakaian'))),
                    $this->format(array_sum(array_column($d['fuels'], 'pengiriman'))),
                    $this->format(array_sum(array_column($d['fuels'], 'sisa'))),
                ],
            ),
            $this->section(
                'Pemakaian Pelumas (liter)',
                ['Jenis Pelumas', 'Tambah', 'Ganti', 'Total Pemakaian'],
                array_map(fn (array $p): array => [
                    $p['label'],
                    $this->format($p['tambah'], 'decimal'),
                    $this->format($p['ganti'], 'decimal'),
                    $this->format($p['tambah'] + $p['ganti'], 'decimal'),
                ], $d['pelumas']),
                footer: $d['pelumas'] === [] ? null : [
                    'TOTAL PELUMAS',
                    $this->format(array_sum(array_column($d['pelumas'], 'tambah')), 'decimal'),
                    $this->format(array_sum(array_column($d['pelumas'], 'ganti')), 'decimal'),
                    $this->format(array_sum(array_column($d['pelumas'], 'tambah')) + array_sum(array_column($d['pelumas'], 'ganti')), 'decimal'),
                ],
            ),
            $this->monthly('Pemakaian BBM 6 Bulan Terakhir (kL)', $d['fuel_monthly']),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<Section>
     */
    private function harSections(array $d): array
    {
        $trend = $d['trend'];
        $cost = $d['cost'];
        $cumWo = $this->cumulative($trend['series'][0]['values']);
        $cumSr = $this->cumulative($trend['series'][1]['values']);
        $cumCost = $this->cumulative(array_map(fn (float|int $a, float|int $b): float => (float) $a + (float) $b, $cost['series'][0]['values'], $cost['series'][1]['values']));

        return [
            $this->section(
                'Work Order, Service Request & Biaya 6 Bulan Terakhir',
                ['Bulan', 'Work Order', 'Kumulatif WO', 'Service Request', 'Kumulatif SR', 'Biaya Jasa', 'Biaya Material', 'Kumulatif Biaya'],
                array_map(fn (string $label, int $i): array => [
                    $label, $this->format($trend['series'][0]['values'][$i]), $this->format($cumWo[$i]), $this->format($trend['series'][1]['values'][$i]), $this->format($cumSr[$i]),
                    $this->format($cost['series'][0]['values'][$i], 'rupiah'), $this->format($cost['series'][1]['values'][$i], 'rupiah'), $this->format($cumCost[$i], 'rupiah'),
                ], $trend['labels'], array_keys($trend['labels'])),
                footer: [
                    'TOTAL 6 BULAN',
                    $this->format(array_sum($trend['series'][0]['values'])),
                    $this->format(end($cumWo) ?: 0),
                    $this->format(array_sum($trend['series'][1]['values'])),
                    $this->format(end($cumSr) ?: 0),
                    $this->format(array_sum($cost['series'][0]['values']), 'rupiah'),
                    $this->format(array_sum($cost['series'][1]['values']), 'rupiah'),
                    $this->format(end($cumCost) ?: 0, 'rupiah'),
                ],
            ),
            $this->section(
                'Work Order per Jenis Pemeliharaan',
                ['Jenis', 'Jumlah WO'],
                array_map(fn (array $r): array => [$r['label'], $this->format($r['value'])], $d['by_type']),
                footer: $d['by_type'] === [] ? null : ['TOTAL WORK ORDER', $this->format(array_sum(array_column($d['by_type'], 'value')))],
            ),
            $this->section(
                'Work Order per Status',
                ['Status', 'Jumlah WO'],
                array_map(fn (array $r): array => [$r['label'], $this->format($r['value'])], $d['by_status']),
                footer: $d['by_status'] === [] ? null : ['TOTAL WORK ORDER', $this->format(array_sum(array_column($d['by_status'], 'value')))],
            ),
            $this->section('Backlog Work Order (belum selesai)', ['No. WO', 'Aset', 'Uraian Pekerjaan', 'Prioritas', 'Status', 'Umur (hari)'], array_map(fn (array $w): array => [
                $w['code'], $w['asset'], $w['task'], $w['priority'], $w['status'], (string) $w['age'],
            ], $d['backlog'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<Section>
     */
    private function k3Sections(array $d): array
    {
        $points = $d['s_curve']['points'];
        $last = collect($points)->filter(fn (array $p): bool => $p['real'] !== null)->last();
        $plan = (int) (end($points)['plan'] ?? 0);

        return [
            $this->section('Realisasi Kegiatan K3 (Time Frame)', ['Uraian', 'Jumlah'], [
                ['Rencana kegiatan sebulan (hari-kegiatan)', $this->format($plan)],
                ['Rencana s.d. hari ini', $this->format($last['plan'] ?? 0)],
                ['Realisasi s.d. hari ini', $this->format($last['real'] ?? 0)],
                ['Capaian s.d. hari ini', ($last['plan'] ?? 0) > 0 ? $this->format(round($last['real'] / $last['plan'] * 100, 1), 'decimal').' %' : '—'],
            ]),
            $this->section('Kepatuhan Kegiatan K3 per Minggu (%)', ['Unit', ...$d['compliance']['columns']], array_map(fn (array $r): array => [
                $r['label'], ...array_map(fn (?int $v): string => $v === null ? '—' : (string) $v, $r['values']),
            ], $d['compliance']['rows'])),
            $this->section(
                'Status Sertifikat Alat',
                ['Status', 'Jumlah'],
                array_map(fn (array $r): array => [$r['label'], $this->format($r['value'])], $d['cert_status']),
                footer: ['TOTAL SERTIFIKAT', $this->format(array_sum(array_column($d['cert_status'], 'value')))],
            ),
            $this->section(
                'Status APAR / APAB',
                ['Status', 'Jumlah Tabung'],
                array_map(fn (array $r): array => [$r['label'], $this->format($r['value'])], $d['apar_status']),
                footer: ['TOTAL TABUNG APAR / APAB', $this->format(array_sum(array_column($d['apar_status'], 'value')))],
            ),
            $this->section(
                'Patroli Security per Lokasi',
                ['Lokasi', 'Jumlah Scan'],
                array_map(fn (array $r): array => [$r['label'], $this->format($r['value'])], $d['patrol_top']),
                footer: $d['patrol_top'] === [] ? null : ['TOTAL SCAN PATROLI', $this->format(array_sum(array_column($d['patrol_top'], 'value')))],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<Section>
     */
    private function logistikSections(array $d): array
    {
        $in = $d['fuel_flow']['series'][0]['values'];
        $out = $d['fuel_flow']['series'][1]['values'];
        $cumIn = $this->cumulative($in);
        $cumOut = $this->cumulative($out);

        return [
            $this->section(
                'Penerimaan vs Pemakaian BBM 6 Bulan Terakhir & Akumulasi (kL)',
                ['Bulan', 'Penerimaan', 'Pemakaian', 'Selisih', 'Kumulatif Penerimaan', 'Kumulatif Pemakaian', 'Kumulatif Selisih'],
                array_map(fn (string $label, int $i): array => [
                    $label, $this->format($in[$i], 'decimal'), $this->format($out[$i], 'decimal'), $this->format($in[$i] - $out[$i], 'decimal'),
                    $this->format($cumIn[$i], 'decimal'), $this->format($cumOut[$i], 'decimal'), $this->format($cumIn[$i] - $cumOut[$i], 'decimal'),
                ], $d['fuel_flow']['labels'], array_keys($d['fuel_flow']['labels'])),
                footer: [
                    'TOTAL 6 BULAN',
                    $this->format(array_sum($in), 'decimal'),
                    $this->format(array_sum($out), 'decimal'),
                    $this->format(array_sum($in) - array_sum($out), 'decimal'),
                    $this->format(end($cumIn) ?: 0, 'decimal'),
                    $this->format(end($cumOut) ?: 0, 'decimal'),
                    $this->format((end($cumIn) ?: 0) - (end($cumOut) ?: 0), 'decimal'),
                ],
            ),
            $this->section('Level Tangki Timbun BBM', ['Unit', 'Isi (%)', 'Kapasitas (kL)'], array_map(fn (array $t): array => [$t['label'], (string) $t['value'], $this->format($t['capacity'], 'decimal')], $d['tanks'])),
            $this->section(
                'Status Stok Tools & Material',
                ['Status', 'Jumlah Item'],
                array_map(fn (array $r): array => [$r['label'], $this->format($r['value'])], $d['category']),
                footer: ['TOTAL ITEM MATERIAL', $this->format(array_sum(array_column($d['category'], 'value')))],
            ),
            $this->section('Material Stok Kritis (stok akhir ≤ ROP)', ['Kode', 'Material', 'Stok Akhir', 'ROP', 'Satuan', 'Kekurangan'], array_map(fn (array $m): array => [
                $m['code'], $m['name'], $this->format($m['stock'], 'decimal'), $this->format($m['min'], 'decimal'), $m['uom'], $this->format(max(0, $m['min'] - $m['stock']), 'decimal'),
            ], $d['critical'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<Section>
     */
    private function pdmSections(array $d): array
    {
        return [
            $this->monthly('Rencana vs Realisasi Prediktif 6 Bulan Terakhir (hari)', $d['realisasi']),
            $this->section(
                'Kondisi Aset (uji vibrasi terakhir)',
                ['Kondisi', 'Jumlah Aset'],
                array_map(fn (array $r): array => [$r['label'], $this->format($r['value'])], $d['condition']),
                footer: ['TOTAL ASET DIUJI', $this->format(array_sum(array_column($d['condition'], 'value')))],
            ),
            $this->section('Kesehatan Aset', ['Aset', 'Vibrasi Maks (mm/s)', 'Kualitas Pelumas', 'Status'], array_map(fn (array $a): array => [
                $a['asset'], $a['vibration'] === null ? '—' : $this->format($a['vibration'], 'decimal'), $a['oil'], $a['status'],
            ], $d['assets'])),
            $this->monthly('Vibrasi Tertinggi per Bulan (mm/s)', $d['vibration'], 'decimal'),
        ];
    }

    /**
     * A month × series table from a chart payload.
     *
     * @param  array{labels: list<string>, series: list<array{name: string, values: list<float|int>}>}  $chart
     * @return Section
     */
    private function monthly(string $title, array $chart, string $format = 'decimal'): array
    {
        return $this->section(
            $title,
            ['Bulan', ...array_column($chart['series'], 'name')],
            array_map(fn (string $label, int $i): array => [
                $label, ...array_map(fn (array $s): string => $this->format($s['values'][$i] ?? 0, $format), $chart['series']),
            ], $chart['labels'], array_keys($chart['labels'])),
            footer: $chart['series'] === [] ? null : [
                'TOTAL 6 BULAN',
                ...array_map(fn (array $s): string => $this->format(array_sum($s['values']), $format), $chart['series']),
            ],
        );
    }

    /**
     * Status of the module's Laporan Project / Pengusahaan of the period.
     *
     * @return Section
     */
    private function reportStatus(string $module, Unit $unit, int $month, int $year): array
    {
        return $this->section('Status Laporan', ['Laporan', 'Status', 'Disahkan'], array_map(function (ReportModule $report) use ($unit, $month, $year): array {
            $workflow = $this->workflows->find($report, $unit->id, $month, $year);

            return [
                $report->label(),
                $workflow === null ? 'Belum diajukan' : $report->statusLabel($this->workflows->status($workflow)),
                $workflow?->finalized_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
            ];
        }, self::MODULES[$module][3]));
    }

    /**
     * Kelengkapan input of the module's menus for the unit.
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<string>  $groups
     * @return Section
     */
    private function inputCompleteness(Collection $units, int $month, int $year, array $groups): array
    {
        $data = $this->completeness->build($units, $month, $year, groups: $groups);
        $cells = $data['units'][0]['cells'] ?? [];
        $totalFilled = 0;
        $totalExpected = 0;
        $percents = [];

        $rows = array_map(function (array $entry) use ($cells, &$totalFilled, &$totalExpected, &$percents): array {
            $filled = (int) ($cells[$entry['key']]['filled'] ?? 0);
            $expected = (int) ($cells[$entry['key']]['expected'] ?? 0);
            $pct = $cells[$entry['key']]['percent'] ?? null;
            $totalFilled += $filled;
            $totalExpected += $expected;
            if ($pct !== null) {
                $percents[] = (float) $pct;
            }

            return [
                InputCatalog::GROUPS[$entry['group']] ?? $entry['group'],
                $entry['label'],
                (string) $filled,
                (string) $expected,
                $pct === null ? '—' : $pct.' %',
            ];
        }, $data['entries']);

        $avgPct = $percents === [] ? 0.0 : round(array_sum($percents) / count($percents), 1);

        return $this->section(
            'Kelengkapan Input',
            ['Kelompok', 'Menu Input', 'Terisi', 'Target', 'Kelengkapan'],
            $rows,
            note: 'Target dihitung sampai hari ini untuk bulan berjalan.',
            footer: [
                'TOTAL & RATA-RATA KELENGKAPAN',
                count($rows).' Menu Input',
                (string) $totalFilled,
                (string) $totalExpected,
                $this->format($avgPct, 'decimal').' %',
            ],
        );
    }

    /**
     * A chart of the resume as a 3D image: columns (grouped / stacked, with a
     * line), 3D lines, 3D pie (komposisi) or donut (status), gauges and
     * horizontal bars; the heatmap becomes 3D columns per week.
     *
     * @param  array<string, mixed>  $chart
     */
    private function image(array $chart): string
    {
        $named = fn (array $series): array => array_combine(array_column($series, 'name'), array_column($series, 'values')) ?: [];

        return match ($chart['type']) {
            'bar', 'stacked' => $this->chart3d->columns('', $chart['labels'], $named($chart['series']), $chart['type'] === 'stacked', (bool) ($chart['line_last'] ?? false)),
            'line' => $this->chart3d->lines('', $chart['labels'], $named($chart['series']), isset($chart['threshold']) ? (float) $chart['threshold'] : null, $chart['threshold_label'] ?? null),
            'scurve' => $this->chart3d->lines('', array_map(fn (array $p): string => (string) $p['day'], $chart['points']), [
                'Rencana kumulatif' => array_column($chart['points'], 'plan'),
                'Realisasi kumulatif' => array_column($chart['points'], 'real'),
            ]),
            'donut' => str_starts_with($chart['title'], 'Komposisi')
                ? $this->chart3d->pie('', array_column($chart['data'], 'label'), array_column($chart['data'], 'value'))
                : $this->chart3d->donut('', array_column($chart['data'], 'label'), array_column($chart['data'], 'value')),
            'gauge' => $this->chart3d->gauges('', $chart['items']),
            'hbar', 'level' => $this->chart3d->hbars('', $chart['data'], $chart['type'] === 'level'),
            'heatmap' => $this->chart3d->columns('', $chart['columns'], array_combine(
                array_column($chart['rows'], 'label'),
                array_map(fn (array $row): array => array_map(fn (?int $v): float => (float) ($v ?? 0), $row['values']), $chart['rows']),
            ) ?: []),
        };
    }

    /**
     * Running total of a series.
     *
     * @param  list<float|int>  $values
     * @return list<float>
     */
    private function cumulative(array $values): array
    {
        $sum = 0.0;

        return array_map(function (float|int $value) use (&$sum): float {
            $sum += (float) $value;

            return round($sum, 2);
        }, array_values($values));
    }

    /**
     * Direction of a KPI against last month: naik / turun / tetap, and
     * whether that is membaik or memburuk for this KPI.
     *
     * @param  array{delta: float|null, good: string}  $kpi
     * @return array{delta: float|null, trend: string|null, assessment: string|null}
     */
    private function direction(array $kpi): array
    {
        $delta = $kpi['delta'];
        if ($delta === null) {
            return ['delta' => null, 'trend' => null, 'assessment' => null];
        }
        if (abs($delta) < 0.05) {
            return ['delta' => 0.0, 'trend' => 'tetap', 'assessment' => 'stabil'];
        }
        $up = $delta > 0;

        return ['delta' => $delta, 'trend' => $up ? 'naik' : 'turun', 'assessment' => $up === ($kpi['good'] === 'up') ? 'membaik' : 'memburuk'];
    }

    /**
     * The direction of a six-month series: the last month against the
     * average of the months before it that have a figure.
     *
     * @param  list<float|int>  $values
     */
    private function seriesTrend(array $values): ?string
    {
        $last = (float) (end($values) ?: 0);
        $before = array_values(array_filter(array_slice($values, 0, -1), fn (float|int $v): bool => (float) $v !== 0.0));
        if ($before === []) {
            return null;
        }
        $average = array_sum($before) / count($before);
        $change = $average == 0.0 ? 0.0 : ($last - $average) / abs($average) * 100;

        return match (true) {
            abs($change) < 5 => 'stabil (± rata-rata '.count($before).' bulan sebelumnya)',
            $change > 0 => 'naik '.$this->format(abs($change), 'decimal').'% di atas rata-rata '.count($before).' bulan sebelumnya',
            default => 'turun '.$this->format(abs($change), 'decimal').'% di bawah rata-rata '.count($before).' bulan sebelumnya',
        };
    }

    /**
     * "Ringkasan Arah Data": plain sentences reading the direction of every
     * KPI and the module's notable figures, so the reader sees at once where
     * the data is heading.
     *
     * @param  array<string, mixed>  $d
     * @return list<array{tone: string, text: string}>
     */
    private function insights(string $module, array $d): array
    {
        $lines = [];
        $fmt = fn (array $kpi): string => $kpi['value'] === null ? '—' : trim($this->format($kpi['value'], $kpi['format']).' '.($kpi['unit'] ?? ''));

        foreach ($d['kpis'] as $kpi) {
            $dir = $this->direction($kpi);
            $lines[] = match (true) {
                $kpi['value'] === null => ['tone' => 'neutral', 'text' => "{$kpi['label']}: belum ada data sumbernya."],
                $dir['trend'] === null => ['tone' => 'neutral', 'text' => "{$kpi['label']} bulan ini {$fmt($kpi)} (belum ada angka bulan lalu sebagai pembanding)."],
                $dir['trend'] === 'tetap' => ['tone' => 'neutral', 'text' => "{$kpi['label']} stabil di {$fmt($kpi)}, sama dengan bulan lalu."],
                default => ['tone' => $dir['assessment'] === 'membaik' ? 'success' : 'danger', 'text' => "{$kpi['label']} {$dir['trend']} ".$this->format(abs($dir['delta']), 'decimal')."% dibanding bulan lalu menjadi {$fmt($kpi)} — {$dir['assessment']}."],
            };
        }

        $add = function (?string $text, string $tone = 'info') use (&$lines): void {
            if ($text !== null) {
                $lines[] = ['tone' => $tone, 'text' => $text];
            }
        };
        $top = fn (array $data): ?array => collect($data)->sortByDesc('value')->first(fn (array $row): bool => $row['value'] > 0);

        switch ($module) {
            case 'operasi':
                $nett = $d['daily']['series'][0]['values'];
                if (array_sum($nett) > 0) {
                    $best = array_keys($nett, max($nett))[0];
                    $add('Akumulasi kWh netto bulan ini '.$this->format(array_sum($nett), 'decimal').' MWh; produksi harian tertinggi pada tanggal '.$d['daily']['labels'][$best].' ('.$this->format($nett[$best], 'decimal').' MWh), rata-rata '.$this->format(array_sum($nett) / max(1, count(array_filter($nett))), 'decimal').' MWh per hari produksi.');
                }
                if (collect($d['units'])->every(fn (array $u): bool => $u['eaf'] === null)) {
                    $add('EAF, EFOR & CF belum dapat dihitung: belum ada jam operasi mesin pada periode ini.', 'neutral');
                } else {
                    foreach ($d['gauges'] as $g) {
                        $ok = $g['good'] === 'up' ? $g['value'] >= $g['target'] : $g['value'] <= $g['target'];
                        $add("{$g['label']} ".$this->format($g['value'], 'decimal').'% — '.($ok ? 'memenuhi' : 'belum memenuhi').' target '.($g['good'] === 'up' ? '≥ ' : '≤ ').$this->format($g['target'], 'decimal').'%.', $ok ? 'success' : 'warning');
                    }
                }
                foreach ($d['fuel_monthly']['series'] as $serie) {
                    $trend = $this->seriesTrend($serie['values']);
                    $add($trend === null ? null : "Pemakaian {$serie['name']} {$trend}.");
                }
                if (($jam = $top($d['jam_mesin'])) !== null) {
                    $add("Jam pembangkit didominasi {$jam['label']} (".$this->format($jam['value'], 'decimal').' jam).');
                }
                break;
            case 'pemeliharaan':
                $add(($t = $this->seriesTrend($d['trend']['series'][0]['values'])) === null ? null : "Jumlah Work Order {$t}.");
                $add(($t = $this->seriesTrend(array_map(fn ($a, $b) => $a + $b, $d['cost']['series'][0]['values'], $d['cost']['series'][1]['values']))) === null ? null : "Biaya pemeliharaan {$t}.");
                if (($type = $top($d['by_type'])) !== null) {
                    $add("Jenis WO terbanyak bulan ini: {$type['label']} ({$type['value']} WO).");
                }
                if ($d['backlog'] !== []) {
                    $oldest = collect($d['backlog'])->sortByDesc('age')->first();
                    $add('Masih ada '.count($d['backlog'])." WO belum selesai di daftar backlog; yang terlama {$oldest['code']} ({$oldest['age']} hari, {$oldest['status']}).", 'warning');
                }
                break;
            case 'k3':
                $last = collect($d['s_curve']['points'])->filter(fn (array $p): bool => $p['real'] !== null)->last();
                if (($last['plan'] ?? 0) > 0) {
                    $pct = $last['real'] / $last['plan'] * 100;
                    $add('Realisasi kegiatan K3 s.d. hari ini '.$this->format($pct, 'decimal')."% dari rencana ({$last['real']} dari {$last['plan']} hari-kegiatan) — ".($pct >= 100 ? 'sesuai/di atas rencana' : 'tertinggal dari rencana').'.', $pct >= 100 ? 'success' : 'warning');
                }
                $expired = collect($d['cert_status'])->firstWhere('label', 'Expired')['value'] ?? 0;
                $soon = collect($d['cert_status'])->firstWhere('label', 'Segera Habis')['value'] ?? 0;
                if ($expired + $soon > 0) {
                    $add("Sertifikat alat: {$expired} expired dan {$soon} segera habis — perlu dijadwalkan uji ulang.", $expired > 0 ? 'danger' : 'warning');
                }
                if (($loc = $top($d['patrol_top'])) !== null) {
                    $add("Lokasi patroli paling sering di-scan: {$loc['label']} ({$loc['value']} scan).");
                }
                break;
            case 'logistik':
                $in = array_sum($d['fuel_flow']['series'][0]['values']);
                $out = array_sum($d['fuel_flow']['series'][1]['values']);
                if ($in + $out > 0) {
                    $add('Akumulasi 6 bulan: penerimaan BBM '.$this->format($in, 'decimal').' kL vs pemakaian '.$this->format($out, 'decimal').' kL — cadangan '.($in >= $out ? 'bertambah ' : 'berkurang ').$this->format(abs($in - $out), 'decimal').' kL.', $in >= $out ? 'success' : 'warning');
                }
                if ($d['tanks'] !== []) {
                    $low = collect($d['tanks'])->sortBy('value')->first();
                    $add("Level tangki terendah {$low['label']} ({$low['value']}% dari kapasitas).", $low['value'] < 30 ? 'danger' : 'info');
                }
                if ($d['critical'] !== []) {
                    $add(count($d['critical']).' item material di bawah ROP; paling kurang: '.$d['critical'][0]['name'].'.', 'warning');
                }
                break;
            case 'pdm':
                $add(($t = $this->seriesTrend($d['realisasi']['series'][1]['values'])) === null ? null : "Realisasi pengukuran prediktif {$t}.");
                $alarm = collect($d['assets'])->where('status', 'Alarm');
                if ($alarm->isNotEmpty()) {
                    $add('Aset status Alarm: '.$alarm->pluck('asset')->implode(', ').' — perlu tindak lanjut pemeliharaan.', 'danger');
                }
                $max = collect($d['assets'])->sortByDesc('vibration')->first();
                if (($max['vibration'] ?? null) !== null) {
                    $add("Vibrasi tertinggi {$max['asset']}: ".$this->format($max['vibration'], 'decimal')." mm/s ({$max['status']}).");
                }
                break;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     * @param  list<string>|null  $footer
     * @return Section
     */
    private function section(string $title, array $columns, array $rows, ?string $note = null, ?array $footer = null): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => array_values($rows)]
            + ($footer === null ? [] : ['footer' => $footer])
            + ($note === null ? [] : ['note' => $note]);
    }

    private function format(float|int|null $value, string $format = 'number'): string
    {
        $value = (float) ($value ?? 0);

        return match ($format) {
            'rupiah' => 'Rp '.number_format($value, 0, ',', '.'),
            'decimal' => number_format($value, 2, ',', '.'),
            'decimal3' => number_format($value, 3, ',', '.'),
            default => number_format($value, 0, ',', '.'),
        };
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function fuels(Unit $unit): array
    {
        return array_map(fn (array $fuel): array => ['code' => $fuel['code'], 'name' => $fuel['name']], $this->fuelTypes->forUnit($unit));
    }

    /**
     * @return list<string>
     */
    private function lubricants(Unit $unit): array
    {
        return LubricantType::query()->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->pluck('name')->unique()->values()->all();
    }
}
