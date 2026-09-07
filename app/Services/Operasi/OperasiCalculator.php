<?php

namespace App\Services\Operasi;

use App\Enums\CalibrationFactorType;
use App\Enums\StatusCodeCategory;
use App\Models\CalibrationFactor;
use App\Models\DailyEngineReport;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single home for every operasi calculation. The input grid, the reports,
 * and the berita acara all derive their numbers here so a formula is never
 * duplicated (modul-operasi.md §5).
 *
 * Nothing in here is stored: opening stands carry over, hours come from the
 * Star-Stop log, and production / consumption are computed on read.
 */
class OperasiCalculator
{
    /**
     * The editable meter fields a user types per day.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = [
        'kwh_produksi_stand_akhir',
        'kwh_pakai_sendiri_stand_akhir',
        'beban_puncak_pagi_kw',
        'beban_puncak_malam_kw',
        'pemakaian_pelumas_liter',
        'flowmeter_hsd_stand_akhir',
        'flowmeter_hsd_tambah_liter',
        'flowmeter_mfo_stand_akhir',
        'flowmeter_mfo_tambah_liter',
        'air_pps_stand_akhir',
        'air_softener_stand_akhir',
        'catatan',
    ];

    /**
     * Build the full daily-engine grid for one machine and one month: one row
     * per calendar day with stored inputs, carried-over opening stands, and the
     * derived production / consumption columns, plus the period subtotals.
     *
     * @return array{rows: list<array<string, mixed>>, summary: array<string, array<string, float>>}
     */
    public function buildDailyEngineGrid(Machine $engine, int $month, int $year): array
    {
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $stored = DailyEngineReport::query()
            ->where('engine_id', $engine->getKey())
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->get()
            ->keyBy(fn (DailyEngineReport $report): int => (int) $report->report_date->day);

        $factors = [
            'kwh' => $this->factorFor($engine, CalibrationFactorType::Kwh, $year, $month),
            'hsd' => $this->factorFor($engine, CalibrationFactorType::Hsd, $year, $month),
            'mfo' => $this->factorFor($engine, CalibrationFactorType::Mfo, $year, $month),
        ];

        $usesMfo = $engine->fuel_type?->usesMfo() ?? false;

        // Opening stands for day 1 come from the last reading before this month.
        $previous = $this->closingBefore($engine, Carbon::create($year, $month, 1)->startOfDay());

        $rows = [];

        foreach (range(1, $totalDays) as $day) {
            $report = $stored->get($day);

            $row = [
                'day' => $day,
                'report_date' => Carbon::create($year, $month, $day)->toDateString(),
            ];

            foreach (self::EDITABLE_FIELDS as $field) {
                $row[$field] = $report?->{$field};
            }

            $row['kwh_produksi_stand_awal'] = $previous['kwh_produksi_stand_akhir'];
            $row['kwh_pakai_sendiri_stand_awal'] = $previous['kwh_pakai_sendiri_stand_akhir'];
            $row['flowmeter_hsd_stand_awal'] = $previous['flowmeter_hsd_stand_akhir'];
            $row['flowmeter_mfo_stand_awal'] = $usesMfo ? $previous['flowmeter_mfo_stand_akhir'] : null;

            $row['kwh_produksi'] = $this->meterDelta(
                $row['kwh_produksi_stand_akhir'],
                $row['kwh_produksi_stand_awal'],
            ) * $factors['kwh'];

            $row['kwh_pakai_sendiri'] = $this->meterDelta(
                $row['kwh_pakai_sendiri_stand_akhir'],
                $row['kwh_pakai_sendiri_stand_awal'],
            ) * $factors['kwh'];

            $row['kwh_netto'] = $row['kwh_produksi'] === null || $row['kwh_pakai_sendiri'] === null
                ? null
                : $row['kwh_produksi'] - $row['kwh_pakai_sendiri'];

            $row['pemakaian_hsd'] = $this->fuelUsage(
                $row['flowmeter_hsd_stand_akhir'],
                $row['flowmeter_hsd_stand_awal'],
                $row['flowmeter_hsd_tambah_liter'],
                $factors['hsd'],
            );

            $row['pemakaian_mfo'] = $usesMfo
                ? $this->fuelUsage(
                    $row['flowmeter_mfo_stand_akhir'],
                    $row['flowmeter_mfo_stand_awal'],
                    $row['flowmeter_mfo_tambah_liter'],
                    $factors['mfo'],
                )
                : null;

            $row['is_complete'] = $report !== null
                && $row['kwh_produksi_stand_akhir'] !== null;

            $rows[] = $row;

            // This day's closing stands become tomorrow's opening stands.
            $previous = [
                'kwh_produksi_stand_akhir' => $this->toFloat($row['kwh_produksi_stand_akhir']) ?? $previous['kwh_produksi_stand_akhir'],
                'kwh_pakai_sendiri_stand_akhir' => $this->toFloat($row['kwh_pakai_sendiri_stand_akhir']) ?? $previous['kwh_pakai_sendiri_stand_akhir'],
                'flowmeter_hsd_stand_akhir' => $this->toFloat($row['flowmeter_hsd_stand_akhir']) ?? $previous['flowmeter_hsd_stand_akhir'],
                'flowmeter_mfo_stand_akhir' => $this->toFloat($row['flowmeter_mfo_stand_akhir']) ?? $previous['flowmeter_mfo_stand_akhir'],
            ];
        }

        return [
            'rows' => $rows,
            'summary' => $this->summarise($rows),
        ];
    }

    /**
     * The machine's hours for a month, grouped by status-code category. Hours
     * are derived from the Star-Stop log durations, never typed in. Standby is
     * the balance of the month's available hours after operating, maintenance
     * and disturbance hours (modul-operasi.md §5.5).
     *
     * @return array{operasi: float, har: float, gangguan: float, standby: float, total: float}
     */
    public function hoursSummary(Machine $engine, int $month, int $year): array
    {
        $totalHours = (float) Carbon::create($year, $month, 1)->daysInMonth * 24;

        $minutesByCategory = EngineStatusLog::query()
            ->where('engine_id', $engine->getKey())
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->join('unit_status_codes', 'engine_status_logs.status_code_id', '=', 'unit_status_codes.id')
            ->groupBy('unit_status_codes.category')
            ->selectRaw('unit_status_codes.category as category, SUM(engine_status_logs.duration_minutes) as minutes')
            ->pluck('minutes', 'category');

        $hours = fn (StatusCodeCategory $category): float => (float) ($minutesByCategory[$category->value] ?? 0) / 60;

        $operasi = $hours(StatusCodeCategory::Operasi);
        $har = $hours(StatusCodeCategory::Har);
        $gangguan = $hours(StatusCodeCategory::Gangguan);

        return [
            'operasi' => round($operasi, 2),
            'har' => round($har, 2),
            'gangguan' => round($gangguan, 2),
            'standby' => round(max(0, $totalHours - ($operasi + $har + $gangguan)), 2),
            'total' => round($totalHours, 2),
        ];
    }

    /**
     * Specific Fuel Consumption for a period (modul-operasi.md §5.4). Bruto uses
     * gross production; netto uses production net of own use. Returns null where
     * the denominator is zero, so the report can show a dash rather than divide.
     *
     * @return array{bruto: float|null, netto: float|null}
     */
    public function sfc(float $totalBbm, float $kwhProduksi, float $kwhPakaiSendiri): array
    {
        $netProduction = $kwhProduksi - $kwhPakaiSendiri;

        return [
            'bruto' => $kwhProduksi > 0 ? round($totalBbm / $kwhProduksi, 4) : null,
            'netto' => $netProduction > 0 ? round($totalBbm / $netProduction, 4) : null,
        ];
    }

    /**
     * The calibration factor for a machine at a point in time: the newest row
     * that is in effect, machine-specific taking precedence over unit-wide.
     * Defaults to 1.0 when none has been configured.
     */
    public function factorFor(Machine $engine, CalibrationFactorType $type, int $year, int $month): float
    {
        $asOf = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $factor = CalibrationFactor::query()
            ->where('unit_id', $engine->unit_id)
            ->where('factor_type', $type->value)
            ->where(function ($query) use ($engine): void {
                $query->where('engine_id', $engine->getKey())->orWhereNull('engine_id');
            })
            ->where('effective_date', '<=', $asOf)
            ->orderByRaw('engine_id IS NULL')
            ->orderByDesc('effective_date')
            ->value('value');

        return $factor === null ? 1.0 : (float) $factor;
    }

    /**
     * The subtotals for periode I (days 1–10), II (11–20), III (21–end) and the
     * whole month.
     *
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

    private function toFloat(int|float|string|null $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function meterDelta(int|float|string|null $akhir, int|float|string|null $awal): ?float
    {
        if ($akhir === null || $awal === null) {
            return null;
        }

        return (float) $akhir - (float) $awal;
    }

    private function fuelUsage(
        int|float|string|null $akhir,
        int|float|string|null $awal,
        int|float|string|null $tambah,
        float $factor,
    ): ?float {
        $delta = $this->meterDelta($akhir, $awal);

        if ($delta === null) {
            return null;
        }

        return ($delta + (float) ($tambah ?? 0)) * $factor;
    }

    /**
     * The closing stands from the most recent stored reading strictly before the
     * given date, used as the opening stands for the first day of a month.
     *
     * @return array{kwh_produksi_stand_akhir: float|null, kwh_pakai_sendiri_stand_akhir: float|null, flowmeter_hsd_stand_akhir: float|null, flowmeter_mfo_stand_akhir: float|null}
     */
    private function closingBefore(Machine $engine, Carbon $date): array
    {
        $report = DailyEngineReport::query()
            ->where('engine_id', $engine->getKey())
            ->where('report_date', '<', $date->toDateString())
            ->orderByDesc('report_date')
            ->first();

        return [
            'kwh_produksi_stand_akhir' => $report?->kwh_produksi_stand_akhir === null ? null : (float) $report->kwh_produksi_stand_akhir,
            'kwh_pakai_sendiri_stand_akhir' => $report?->kwh_pakai_sendiri_stand_akhir === null ? null : (float) $report->kwh_pakai_sendiri_stand_akhir,
            'flowmeter_hsd_stand_akhir' => $report?->flowmeter_hsd_stand_akhir === null ? null : (float) $report->flowmeter_hsd_stand_akhir,
            'flowmeter_mfo_stand_akhir' => $report?->flowmeter_mfo_stand_akhir === null ? null : (float) $report->flowmeter_mfo_stand_akhir,
        ];
    }
}
