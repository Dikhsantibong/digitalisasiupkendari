<?php

namespace App\Services\Reports;

use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Monitoring\InputCatalog;
use App\Services\Monitoring\InputCompleteness;
use App\Support\Indonesian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Laporan UL (PRD.md §8.13): the monthly reports of a service unit (UL) built
 * automatically from every unit under it — the accumulated figures of the
 * Laporan Project inputs and of the TL (Akses 2) inputs, a comparison per unit,
 * the 3D charts, the status of the units' reports and the input completeness.
 * One payload for the page, the PDF, the Excel and the PPT bulanan.
 */
class UlReport
{
    /**
     * kind => [judul, parts (ModuleResume module => judul bagian), ReportModule statuses].
     *
     * @var array<string, array{0: string, 1: array<string, string>, 2: list<ReportModule>}>
     */
    public const KINDS = [
        'pengusahaan' => ['Laporan Pengusahaan Pembangkit', [
            'operasi' => 'Laporan Kinerja (Operasi)',
            'pemeliharaan' => 'Laporan Pemeliharaan (HAR)',
            'k3' => 'Laporan K3L',
        ], [ReportModule::Operasi, ReportModule::OperasiPengusahaan, ReportModule::Har, ReportModule::HarPengusahaan, ReportModule::K3, ReportModule::K3Pengusahaan]],
        'logistik' => ['Laporan Logistik', [
            'logistik' => 'Logistik & Gudang',
        ], [ReportModule::Logistik]],
        'k3l' => ['Laporan K3 Lingkungan', [
            'k3' => 'K3 & Lingkungan (K3L)',
        ], [ReportModule::K3, ReportModule::K3Pengusahaan]],
    ];

    /** How many of a part's own charts go into the report (after the per-unit comparisons). */
    private const PART_CHARTS = 6;

    /** How many KPIs get a per-unit comparison chart. */
    private const COMPARED_KPIS = 4;

    public function __construct(
        private readonly ModuleResume $resume,
        private readonly Chart3d $chart3d,
        private readonly InputCompleteness $completeness,
        private readonly ReportWorkflowService $workflows,
        private readonly UlInputLogistik $inputLogistik,
    ) {}

    /** Whether the user may open the Laporan UL (Admin UL & Super Admin). */
    public static function allows(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::LaporanUlView);
    }

    /**
     * @return Collection<int, Unit>
     */
    public function units(ServiceUnit $ul): Collection
    {
        return $ul->units()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $kind, ServiceUnit $ul, int $month, int $year): array
    {
        [$title, $parts, $reports] = self::KINDS[$kind];
        $units = $this->units($ul);
        $periodLabel = Indonesian::monthName($month).' '.$year;

        $built = [];
        foreach ($parts as $module => $partTitle) {
            $built[] = $this->part($module, $partTitle, $units, $month, $year);
        }

        $statuses = $this->reportStatuses($units, $reports, $month, $year);
        $completeness = $this->completenessTable($units, $parts, $month, $year);

        return [
            'kind' => $kind,
            'title' => $title,
            'ul' => ['id' => $ul->id, 'name' => $ul->name],
            'units' => $units->map(fn (Unit $unit): array => ['id' => $unit->id, 'name' => $unit->name])->values()->all(),
            'period' => ['month' => $month, 'year' => $year, 'label' => $periodLabel],
            'meta' => [
                ['label' => 'Unit Layanan (UL)', 'value' => $ul->name],
                ['label' => 'Unit Pembangkit', 'value' => $units->isEmpty() ? '—' : $units->pluck('name')->join(', ')],
                ['label' => 'Periode', 'value' => $periodLabel],
                ['label' => 'Sumber Data', 'value' => 'Input Laporan Project & input divisi (TL) seluruh unit di bawah UL'],
                ['label' => 'Laporan Unit Disahkan', 'value' => $statuses['summary']],
                ['label' => 'Rata-rata Kelengkapan Input', 'value' => $completeness['summary']],
                ['label' => 'Waktu Pembuatan', 'value' => Carbon::now()->timezone(config('app.timezone'))->format('d/m/Y H:i').' WITA'],
            ],
            'parts' => $built,
            'reports' => $statuses['table'],
            'completeness' => $completeness['table'],
        ];
    }

    /**
     * One part (Kinerja, HAR, K3L, Logistik): the UL's accumulated KPIs and
     * charts plus the KPIs of each unit side by side.
     *
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    private function part(string $module, string $title, Collection $units, int $month, int $year): array
    {
        if ($units->isEmpty()) {
            return ['key' => $module, 'title' => $title, 'kpis' => [], 'insights' => [], 'charts' => [], 'unit_table' => null, 'sections' => []];
        }

        $total = $this->resume->aggregate($module, $units, $month, $year);
        $perUnit = $units->map(fn (Unit $unit): array => [
            'unit' => $unit,
            'kpis' => $this->resume->aggregate($module, new Collection([$unit]), $month, $year, withCharts: false)['raw_kpis'],
        ])->values();

        $value = fn (array $kpi): string => $kpi['value'] === null ? '—' : trim($this->resume->formatValue($kpi['value'], $kpi['format']).(($kpi['unit'] ?? '') !== '' ? ' '.$kpi['unit'] : ''));

        $unitTable = [
            'title' => "{$title} — Perbandingan per Unit",
            'columns' => ['Unit', ...array_column($total['raw_kpis'], 'label')],
            'rows' => $perUnit->map(fn (array $row): array => [$row['unit']->name, ...array_map($value, $row['kpis'])])->all(),
            'footer' => ['TOTAL / AKUMULASI UL', ...array_column($total['kpis'], 'value')],
        ];

        // Each of the first KPIs compared between the units (3D horizontal bars).
        $compare = [];
        foreach (array_slice($total['raw_kpis'], 0, self::COMPARED_KPIS) as $index => $kpi) {
            $rows = $perUnit->map(fn (array $row): array => ['label' => $row['unit']->name, 'value' => (float) ($row['kpis'][$index]['value'] ?? 0)])->all();
            $chartTitle = "{$kpi['label']} per Unit".(($kpi['unit'] ?? '') !== '' ? " ({$kpi['unit']})" : '');
            $compare[] = [
                'title' => $chartTitle,
                'subtitle' => 'Perbandingan antar unit di bawah UL',
                'type' => 'hbar',
                'image' => $this->chart3d->hbars($chartTitle, $rows, ($kpi['unit'] ?? '') === '%'),
            ];
        }

        // Laporan Logistik: the TL's Input Logistik (Pemeliharaan Akses 2) of every unit goes in too.
        $extra = $module === 'logistik' ? $this->inputLogistik->build($units, $month, $year) : ['sections' => [], 'insights' => []];

        return [
            'key' => $module,
            'title' => $title,
            // Each indicator also with its average per unit (mean of the units' own values).
            'kpis' => array_map(function (array $kpi, int $index) use ($perUnit, $total): array {
                $raw = $total['raw_kpis'][$index] ?? null;
                $values = $perUnit->map(fn (array $row): ?float => isset($row['kpis'][$index]['value']) ? (float) $row['kpis'][$index]['value'] : null)->filter(fn (?float $v): bool => $v !== null);

                return [...$kpi, 'average' => $raw === null || $values->isEmpty()
                    ? '—'
                    : trim($this->resume->formatValue($values->avg(), $raw['format']).(($raw['unit'] ?? '') !== '' ? ' '.$raw['unit'] : ''))];
            }, $total['kpis'], array_keys($total['kpis'])),
            'insights' => [...$total['insights'], ...$extra['insights']],
            'charts' => [...$compare, ...array_slice($total['charts'], 0, self::PART_CHARTS)],
            'unit_table' => $unitTable,
            'sections' => [...$extra['sections'], ...$total['sections']],
        ];
    }

    /**
     * Status of each unit's Laporan Project / divisi (TL) reports of the period.
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<ReportModule>  $reports
     * @return array{table: array<string, mixed>, summary: string}
     */
    private function reportStatuses(Collection $units, array $reports, int $month, int $year): array
    {
        $final = 0;
        $rows = $units->map(function (Unit $unit) use ($reports, $month, $year, &$final): array {
            return [$unit->name, ...array_map(function (ReportModule $report) use ($unit, $month, $year, &$final): string {
                $workflow = $this->workflows->find($report, $unit->id, $month, $year);
                if ($workflow?->finalized_at !== null) {
                    $final++;
                }

                return $workflow === null ? 'Belum diajukan' : $report->statusLabel($this->workflows->status($workflow));
            }, $reports)];
        })->values()->all();

        $total = $units->count() * count($reports);

        return [
            'table' => [
                'title' => 'Status Laporan Unit (Laporan Project & Laporan Divisi TL)',
                'columns' => ['Unit', ...array_map(fn (ReportModule $report): string => $report->label(), $reports)],
                'rows' => $rows,
            ],
            'summary' => "{$final} dari {$total} laporan",
        ];
    }

    /**
     * Input completeness of each unit for the groups of the report's parts.
     *
     * @param  Collection<int, Unit>  $units
     * @param  array<string, string>  $parts
     * @return array{table: array<string, mixed>, summary: string}
     */
    private function completenessTable(Collection $units, array $parts, int $month, int $year): array
    {
        $groups = array_values(array_unique(array_merge(...array_map(fn (string $module): array => ModuleResume::MODULES[$module][2], array_keys($parts)))));
        $data = $units->isEmpty() ? ['units' => [], 'percent' => null] : $this->completeness->build($units, $month, $year, groups: $groups);
        $percent = fn (?int $value): string => $value === null ? '—' : "{$value}%";

        return [
            'table' => [
                'title' => 'Kelengkapan Input per Unit',
                'columns' => ['Unit', ...array_map(fn (string $group): string => InputCatalog::GROUPS[$group] ?? $group, $groups), 'Rata-rata'],
                'rows' => array_map(fn (array $row): array => [
                    $row['name'],
                    ...array_map(fn (string $group): string => $percent($row['groups'][$group] ?? null), $groups),
                    $percent($row['percent']),
                ], $data['units']),
            ],
            'summary' => $percent($data['percent']),
        ];
    }
}
