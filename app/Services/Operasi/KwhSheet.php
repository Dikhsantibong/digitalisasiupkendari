<?php

namespace App\Services\Operasi;

use App\Enums\CalibrationFactorType;
use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\OperasiPemakaianBbm;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The kWh sheets of Pengusahaan Operasi, all read from one input — the daily
 * kWh meter stands per mesin (Stand kWh Harian, stored on DailyEngineReport):
 *
 * - Stand kWh Harian: per day the PROD and PS stands (awal = yesterday's
 *   akhir), Produksi, Pemakaian Sendiri and kWh Nett;
 * - Stand kWh Meter untuk Transfer Pricing: the month's opening / closing
 *   stand per mesin of the PROD meter (hal 1) and the PS meter (hal 2);
 * - Energi Dibangkit (kWh Nett) and Energi Pemakaian Sendiri per day, with
 *   the machines grouped by merk.
 * - kWh Rekap: kWh produksi, kWh PS and kWh netto side by side;
 * - SFC and SFC Netto: liter BBM (every jenis BBM of the unit, Pemakaian
 *   Bahan Bakar) per kWh produksi / per kWh netto.
 *
 * kWh = (akhir − awal) × faktor koreksi (kalibrasi kWh) × faktor kali of the
 * meter (Master Mesin), computed by {@see OperasiCalculator}.
 */
class KwhSheet
{
    public function __construct(
        private readonly OperasiCalculator $calculator,
        private readonly UnitFuelTypes $fuels,
    ) {}

    /**
     * @return Collection<int, Machine>
     */
    public function machines(Unit $unit): Collection
    {
        return Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return array{id: int, name: string, merk: string|null, type: string|null, serial_number: string|null}
     */
    public function machineInfo(Machine $machine): array
    {
        return [
            'id' => $machine->id,
            'name' => $machine->name,
            'merk' => $machine->merk,
            'type' => $machine->type,
            'serial_number' => $machine->serial_number,
        ];
    }

    /**
     * Stand kWh Harian of every active machine.
     *
     * @return list<array{
     *     machine: array{id: int, name: string, merk: string|null, type: string|null, serial_number: string|null},
     *     faktor_koreksi: float, faktor_kali_produksi: float, faktor_kali_ps: float,
     *     stand_awal_produksi: float|null, stand_awal_ps: float|null,
     *     stand_akhir_produksi: float|null, stand_akhir_ps: float|null,
     *     has_previous: bool,
     *     rows: list<array{day: int, produksi_awal: float|null, produksi_akhir: float|null, produksi: float|null, ps_awal: float|null, ps_akhir: float|null, ps: float|null, nett: float|null}>,
     *     totals: array{produksi: float, ps: float, nett: float}
     * }>
     */
    public function harian(Unit $unit, int $month, int $year): array
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();

        return $this->machines($unit)->map(function (Machine $machine) use ($month, $year, $startOfMonth): array {
            $grid = $this->calculator->buildDailyEngineGrid($machine, $month, $year);
            $rows = [];
            $lastProduksi = null;
            $lastPs = null;

            foreach ($grid['rows'] as $row) {
                $produksiAkhir = $this->number($row['kwh_produksi_stand_akhir']);
                $psAkhir = $this->number($row['kwh_pakai_sendiri_stand_akhir']);
                $produksi = $produksiAkhir === null || $row['kwh_produksi_stand_awal'] === null ? null : round((float) $row['kwh_produksi'], 2);
                $ps = $psAkhir === null || $row['kwh_pakai_sendiri_stand_awal'] === null ? null : round((float) $row['kwh_pakai_sendiri'], 2);

                $rows[] = [
                    'day' => (int) $row['day'],
                    'produksi_awal' => $this->number($row['kwh_produksi_stand_awal']),
                    'produksi_akhir' => $produksiAkhir,
                    'produksi' => $produksi,
                    'ps_awal' => $this->number($row['kwh_pakai_sendiri_stand_awal']),
                    'ps_akhir' => $psAkhir,
                    'ps' => $ps,
                    'nett' => $produksi === null && $ps === null ? null : round(($produksi ?? 0) - ($ps ?? 0), 2),
                ];

                $lastProduksi = $produksiAkhir ?? $lastProduksi;
                $lastPs = $psAkhir ?? $lastPs;
            }

            $produksiTotal = round(array_sum(array_map(fn (array $r): float => (float) $r['produksi'], $rows)), 2);
            $psTotal = round(array_sum(array_map(fn (array $r): float => (float) $r['ps'], $rows)), 2);
            $first = $rows[0] ?? null;

            return [
                'machine' => $this->machineInfo($machine),
                'faktor_koreksi' => $this->calculator->factorFor($machine, CalibrationFactorType::Kwh, $year, $month),
                'faktor_kali_produksi' => (float) $machine->kwh_faktor_kali_produksi ?: 1.0,
                'faktor_kali_ps' => (float) $machine->kwh_faktor_kali_ps ?: 1.0,
                'stand_awal_produksi' => $first['produksi_awal'] ?? null,
                'stand_awal_ps' => $first['ps_awal'] ?? null,
                'stand_akhir_produksi' => $lastProduksi ?? ($first['produksi_awal'] ?? null),
                'stand_akhir_ps' => $lastPs ?? ($first['ps_awal'] ?? null),
                'has_previous' => DailyEngineReport::query()->where('engine_id', $machine->id)->where('report_date', '<', $startOfMonth->toDateString())->exists(),
                'rows' => $rows,
                'totals' => ['produksi' => $produksiTotal, 'ps' => $psTotal, 'nett' => round($produksiTotal - $psTotal, 2)],
            ];
        })->values()->all();
    }

    /**
     * Stand kWh Meter untuk Transfer Pricing: hal 1 (meter produksi) and
     * hal 2 (meter pemakaian sendiri). Hasil akhir is (stand akhir − stand
     * awal) × faktor kali, null when a stand is missing.
     *
     * @param  list<array<string, mixed>>|null  $harian
     * @return array{produksi: list<array<string, mixed>>, ps: list<array<string, mixed>>, total_produksi: float, total_ps: float}
     */
    public function transferPricing(Unit $unit, int $month, int $year, ?array $harian = null): array
    {
        $harian ??= $this->harian($unit, $month, $year);
        // Hasil akhir = (stand akhir − stand awal) × faktor kali of the meter; no kalibrasi factor.
        $line = fn (array $sheet, string $meter): array => [
            ...$sheet['machine'],
            'stand_awal' => $sheet["stand_awal_{$meter}"],
            'stand_akhir' => $sheet["stand_akhir_{$meter}"],
            'faktor_kali' => $sheet["faktor_kali_{$meter}"],
            'hasil' => $sheet["stand_awal_{$meter}"] === null || $sheet["stand_akhir_{$meter}"] === null
                ? null
                : round(($sheet["stand_akhir_{$meter}"] - $sheet["stand_awal_{$meter}"]) * $sheet["faktor_kali_{$meter}"], 2),
        ];

        $produksi = array_map(fn (array $sheet): array => $line($sheet, 'produksi'), $harian);
        $ps = array_map(fn (array $sheet): array => $line($sheet, 'ps'), $harian);

        return [
            'produksi' => $produksi,
            'ps' => $ps,
            'total_produksi' => round(array_sum(array_column($produksi, 'hasil')), 2),
            'total_ps' => round(array_sum(array_column($ps, 'hasil')), 2),
        ];
    }

    /**
     * Energi Dibangkit (kWh Nett, `nett`) or Energi Pemakaian Sendiri (`ps`)
     * per day, machines grouped by merk with a JUMLAH per merk.
     *
     * @param  'produksi'|'nett'|'ps'  $field
     * @param  list<array<string, mixed>>|null  $harian
     * @return array{
     *     groups: list<array{merk: string, machines: list<array<string, mixed>>, totals_by_day: array<int, float>, total: float}>,
     *     values: array<int, array<int, float>>,
     *     totals_by_machine: array<int, float>,
     *     totals_by_day: array<int, float>,
     *     grand_total: float,
     *     days_in_month: int
     * }
     */
    public function energi(Unit $unit, int $month, int $year, string $field, ?array $harian = null): array
    {
        $harian ??= $this->harian($unit, $month, $year);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $values = [];
        foreach ($harian as $sheet) {
            foreach ($sheet['rows'] as $row) {
                $values[$sheet['machine']['id']][$row['day']] = (float) ($row[$field] ?? 0);
            }
        }

        $groups = collect($harian)
            ->groupBy(fn (array $sheet): string => strtoupper(trim((string) ($sheet['machine']['merk'] ?? ''))) ?: 'MESIN')
            ->map(function ($sheets, string $merk) use ($values, $daysInMonth): array {
                $ids = $sheets->pluck('machine.id')->all();
                $byDay = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $byDay[$day] = round(array_sum(array_map(fn (int $id): float => $values[$id][$day] ?? 0.0, $ids)), 2);
                }

                return [
                    'merk' => $merk,
                    'machines' => $sheets->pluck('machine')->values()->all(),
                    'totals_by_day' => $byDay,
                    'total' => round(array_sum($byDay), 2),
                ];
            })
            ->values()
            ->all();

        $totalsByDay = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $totalsByDay[$day] = round(array_sum(array_map(fn (array $group): float => $group['totals_by_day'][$day], $groups)), 2);
        }

        return [
            'groups' => $groups,
            'values' => $values,
            'totals_by_machine' => array_map(fn (array $row): float => round(array_sum($row), 2), $values),
            'totals_by_day' => $totalsByDay,
            'grand_total' => round(array_sum($totalsByDay), 2),
            'days_in_month' => $daysInMonth,
        ];
    }

    /**
     * kWh Rekap: per tanggal and mesin (grouped by merk) the kWh produksi,
     * the kWh pemakaian sendiri and the kWh netto (produksi − PS), all from
     * Stand kWh Harian.
     *
     * @return array{
     *     groups: list<array{merk: string, machines: list<array<string, mixed>>}>,
     *     blocks: list<array{key: string, title: string, values: array<int, array<int, float>>, totals_by_machine: array<int, float>, totals_by_day: array<int, float>, grand_total: float}>,
     *     days_in_month: int
     * }
     */
    public function rekap(Unit $unit, int $month, int $year): array
    {
        $harian = $this->harian($unit, $month, $year);
        $blocks = [];
        $groups = [];

        foreach (['produksi' => 'kWh', 'ps' => 'kWh PS', 'nett' => 'kWh Netto'] as $field => $title) {
            $energi = $this->energi($unit, $month, $year, $field, $harian);
            $groups = array_map(fn (array $group): array => ['merk' => $group['merk'], 'machines' => $group['machines']], $energi['groups']);
            $blocks[] = [
                'key' => $field,
                'title' => $title,
                'values' => $energi['values'],
                'totals_by_machine' => $energi['totals_by_machine'],
                'totals_by_day' => $energi['totals_by_day'],
                'grand_total' => $energi['grand_total'],
            ];
        }

        return [
            'groups' => $groups,
            'blocks' => $blocks,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
        ];
    }

    /**
     * Spesifik Fuel Consumption (liter/kWh) per mesin per tanggal: the liters
     * of every jenis BBM of the unit ({@see UnitFuelTypes}, Pemakaian Bahan
     * Bakar) divided by the kWh produksi (SFC) or the kWh netto (SFC Netto) of
     * Stand kWh Harian; 0 when that kWh is not positive. A day's TOTAL, a
     * machine's JMH and the unit figure divide the summed liters by the
     * summed kWh.
     *
     * @param  'produksi'|'nett'  $basis
     * @return array{
     *     groups: list<array{merk: string, machines: list<array<string, mixed>>}>,
     *     fuels: list<array{code: string, name: string}>,
     *     values: array<int, array<int, float>>,
     *     totals_by_machine: array<int, float>,
     *     totals_by_day: array<int, float>,
     *     grand_total: float,
     *     days_in_month: int
     * }
     */
    public function sfc(Unit $unit, int $month, int $year, string $basis): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $energi = $this->energi($unit, $month, $year, $basis);
        $fuels = array_map(fn (array $fuel): array => ['code' => $fuel['code'], 'name' => $fuel['name']], $this->fuels->forUnit($unit));
        $readings = OperasiPemakaianBbm::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first()?->raw_readings ?? [];
        $machineIds = array_merge(...array_map(fn (array $group): array => array_column($group['machines'], 'id'), $energi['groups'] ?: [['machines' => []]]));
        $ratio = fn (float $liter, float $kwh): float => $kwh > 0 ? round($liter / $kwh, 4) : 0.0;

        $liters = [];
        foreach ($machineIds as $machineId) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $liters[$machineId][$day] = array_sum(array_map(fn (array $fuel): float => (float) ($readings["{$fuel['code']}_{$machineId}"][$day] ?? $readings["{$fuel['code']}_{$machineId}"][(string) $day] ?? 0), $fuels));
            }
        }

        $values = [];
        $totalsByMachine = [];
        foreach ($machineIds as $machineId) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $values[$machineId][$day] = $ratio($liters[$machineId][$day], (float) ($energi['values'][$machineId][$day] ?? 0));
            }
            $totalsByMachine[$machineId] = $ratio(array_sum($liters[$machineId]), array_sum(array_map('floatval', $energi['values'][$machineId] ?? [])));
        }

        $totalsByDay = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayLiter = array_sum(array_map(fn (int $id): float => $liters[$id][$day], $machineIds));
            $totalsByDay[$day] = $ratio($dayLiter, (float) $energi['totals_by_day'][$day]);
        }

        return [
            'groups' => array_map(fn (array $group): array => ['merk' => $group['merk'], 'machines' => $group['machines']], $energi['groups']),
            'fuels' => $fuels,
            'values' => $values,
            'totals_by_machine' => $totalsByMachine,
            'totals_by_day' => $totalsByDay,
            'grand_total' => $ratio(array_sum(array_map('array_sum', $liters)), (float) $energi['grand_total']),
            'days_in_month' => $daysInMonth,
        ];
    }

    private function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
