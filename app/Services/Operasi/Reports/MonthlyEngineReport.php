<?php

namespace App\Services\Operasi\Reports;

use App\Models\Machine;
use App\Models\Unit;
use App\Services\Operasi\OperasiCalculator;
use Illuminate\Support\Carbon;

/**
 * Monthly operating report for a single machine: the daily meter recap with
 * period subtotals, hours split (from Star-Stop), fuel/lubricant use and SFC.
 * All numbers come from {@see OperasiCalculator}.
 */
class MonthlyEngineReport implements OperasiReport
{
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(private readonly OperasiCalculator $calculator) {}

    public function code(): string
    {
        return 'laporan-operasi-bulanan';
    }

    public function title(): string
    {
        return 'Laporan Operasi Bulanan (per Mesin)';
    }

    public function description(): string
    {
        return 'Rekap harian kWh, pemakaian BBM & pelumas, jam operasi/HAR/gangguan, dan SFC untuk satu mesin.';
    }

    public function requiresEngine(): bool
    {
        return true;
    }

    public function page(): string
    {
        return 'operasi/laporan/monthly-engine';
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year, ?Machine $engine): array
    {
        $unit->loadMissing('serviceUnit:id,name');

        $grid = $engine === null
            ? ['rows' => [], 'summary' => []]
            : $this->calculator->buildDailyEngineGrid($engine, $month, $year);

        $hours = $engine === null ? null : $this->calculator->hoursSummary($engine, $month, $year);

        $total = $grid['summary']['total'] ?? [];
        $totalBbm = (float) ($total['pemakaian_hsd'] ?? 0) + (float) ($total['pemakaian_mfo'] ?? 0);

        $sfc = $this->calculator->sfc(
            $totalBbm,
            (float) ($total['kwh_produksi'] ?? 0),
            (float) ($total['kwh_pakai_sendiri'] ?? 0),
        );

        return [
            'unit' => [
                'name' => $unit->name,
                'code' => $unit->code,
                'location' => $unit->location,
                'service_unit' => $unit->serviceUnit?->name,
            ],
            'engine' => $engine === null ? null : [
                'name' => $engine->name,
                'type' => $engine->type,
                'fuel_type' => $engine->fuel_type?->label(),
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => (self::MONTHS[$month] ?? $month).' '.$year,
                'days' => (int) Carbon::create($year, $month, 1)->daysInMonth,
            ],
            'rows' => $grid['rows'],
            'summary' => $grid['summary'],
            'hours' => $hours,
            'total_bbm' => round($totalBbm, 2),
            'sfc' => $sfc,
        ];
    }
}
