<?php

namespace App\Services\Operasi\Reports;

use App\Models\Machine;
use App\Models\Unit;
use App\Services\Operasi\OperasiCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        return 'Laporan Operasi Pembangkit';
    }

    public function description(): string
    {
        return 'Tersusun sesuai daftar isi: resume statistik, seluruh jadwal & laporan operasi (garis merah bila belum ada data), input data aplikasi pembangkit per unit (keseluruhan mesin), dan lampiran — PDF gabungan Portrait & Landscape.';
    }

    public function requiresEngine(): bool
    {
        return false;
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

        if ($engine !== null) {
            $grid = $this->calculator->buildDailyEngineGrid($engine, $month, $year);
            $hours = $this->calculator->hoursSummary($engine, $month, $year);
        } else {
            [$grid, $hours] = $this->buildUnitGrid($unit, $month, $year);
        }

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

    /**
     * Build the daily grid and hours summary aggregated across all active machines in the unit.
     *
     * @return array{0: array{rows: list<array<string, mixed>>, summary: array<string, array<string, float>>}, 1: array{operasi: float, har: float, gangguan: float, standby: float, total: float}|null}
     */
    private function buildUnitGrid(Unit $unit, int $month, int $year): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($machines->isEmpty()) {
            $emptyRows = [];
            foreach (range(1, $daysInMonth) as $day) {
                $emptyRows[] = [
                    'day' => $day,
                    'report_date' => Carbon::create($year, $month, $day)->toDateString(),
                    'kwh_produksi' => null,
                    'kwh_pakai_sendiri' => null,
                    'kwh_netto' => null,
                    'pemakaian_hsd' => null,
                    'pemakaian_mfo' => null,
                    'pemakaian_pelumas_liter' => null,
                    'beban_puncak_pagi_kw' => null,
                    'beban_puncak_malam_kw' => null,
                    'kwh_produksi_stand_akhir' => null,
                    'is_complete' => false,
                ];
            }

            return [['rows' => $emptyRows, 'summary' => []], null];
        }

        $machineGrids = [];
        $hours = [
            'operasi' => 0.0,
            'har' => 0.0,
            'gangguan' => 0.0,
            'standby' => 0.0,
            'total' => 0.0,
        ];

        $usesMfo = $machines->contains(fn (Machine $m): bool => $m->fuel_type?->usesMfo() ?? false);

        foreach ($machines as $machine) {
            $machineGrids[] = $this->calculator->buildDailyEngineGrid($machine, $month, $year);
            $h = $this->calculator->hoursSummary($machine, $month, $year);
            $hours['operasi'] += $h['operasi'];
            $hours['har'] += $h['har'];
            $hours['gangguan'] += $h['gangguan'];
            $hours['standby'] += $h['standby'];
            $hours['total'] += $h['total'];
        }

        $hours = array_map(fn (float $val): float => round($val, 2), $hours);

        $rows = [];
        foreach (range(1, $daysInMonth) as $day) {
            $dayIndex = $day - 1;
            $hasAnyData = false;
            $kwhProd = 0.0;
            $kwhPs = 0.0;
            $hsd = 0.0;
            $mfo = 0.0;
            $pelumas = 0.0;
            $bpPagi = 0.0;
            $bpMalam = 0.0;

            foreach ($machineGrids as $mGrid) {
                $mRow = $mGrid['rows'][$dayIndex] ?? null;
                if ($mRow === null) {
                    continue;
                }

                if ($mRow['kwh_produksi_stand_akhir'] !== null || $mRow['kwh_produksi'] !== null) {
                    $hasAnyData = true;
                }

                if ($mRow['kwh_produksi'] !== null) {
                    $kwhProd += (float) $mRow['kwh_produksi'];
                }
                if ($mRow['kwh_pakai_sendiri'] !== null) {
                    $kwhPs += (float) $mRow['kwh_pakai_sendiri'];
                }
                if ($mRow['pemakaian_hsd'] !== null) {
                    $hsd += (float) $mRow['pemakaian_hsd'];
                }
                if ($mRow['pemakaian_mfo'] !== null) {
                    $mfo += (float) $mRow['pemakaian_mfo'];
                }
                if ($mRow['pemakaian_pelumas_liter'] !== null) {
                    $pelumas += (float) $mRow['pemakaian_pelumas_liter'];
                }
                if ($mRow['beban_puncak_pagi_kw'] !== null) {
                    $bpPagi += (float) $mRow['beban_puncak_pagi_kw'];
                }
                if ($mRow['beban_puncak_malam_kw'] !== null) {
                    $bpMalam += (float) $mRow['beban_puncak_malam_kw'];
                }
            }

            $rows[] = [
                'day' => $day,
                'report_date' => Carbon::create($year, $month, $day)->toDateString(),
                'kwh_produksi' => $hasAnyData ? round($kwhProd, 2) : null,
                'kwh_pakai_sendiri' => $hasAnyData ? round($kwhPs, 2) : null,
                'kwh_netto' => $hasAnyData ? round($kwhProd - $kwhPs, 2) : null,
                'pemakaian_hsd' => $hasAnyData ? round($hsd, 2) : null,
                'pemakaian_mfo' => $usesMfo && $hasAnyData ? round($mfo, 2) : null,
                'pemakaian_pelumas_liter' => $hasAnyData ? (string) round($pelumas, 2) : null,
                'beban_puncak_pagi_kw' => $hasAnyData ? (string) round($bpPagi, 2) : null,
                'beban_puncak_malam_kw' => $hasAnyData ? (string) round($bpMalam, 2) : null,
                'kwh_produksi_stand_akhir' => $hasAnyData ? (string) round($kwhProd, 2) : null,
                'is_complete' => $hasAnyData,
            ];
        }

        $summary = $this->summarise($rows);

        return [['rows' => $rows, 'summary' => $summary], $hours];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, float>>
     */
    private function summarise(array $rows): array
    {
        $collection = collect($rows);

        return [
            'periode_1' => $this->sumRange($collection, 1, 10),
            'periode_2' => $this->sumRange($collection, 11, 20),
            'periode_3' => $this->sumRange($collection, 21, 31),
            'total' => $this->sumRange($collection, 1, 31),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function sumRange(Collection $rows, int $from, int $to): array
    {
        $slice = $rows->whereBetween('day', [$from, $to]);

        $fields = [
            'kwh_produksi', 'kwh_pakai_sendiri', 'kwh_netto',
            'pemakaian_hsd', 'pemakaian_mfo', 'pemakaian_pelumas_liter',
        ];

        $totals = [];
        foreach ($fields as $field) {
            $totals[$field] = (float) $slice->sum(fn (array $row): float => (float) ($row[$field] ?? 0));
        }

        return $totals;
    }
}
