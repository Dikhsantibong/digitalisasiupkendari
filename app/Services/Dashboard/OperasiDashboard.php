<?php

namespace App\Services\Dashboard;

use App\Enums\JamMesinJenis;
use App\Enums\MesinHarianJenis;
use App\Enums\PersediaanJenis;
use App\Models\OperasiPemakaianBbm;
use App\Models\Unit;
use App\Services\Operasi\JamMesinSheet;
use App\Services\Operasi\KinerjaMesinSheet;
use App\Services\Operasi\KinerjaTermalSheet;
use App\Services\Operasi\KwhSheet;
use App\Services\Operasi\MesinHarianSheet;
use App\Services\Operasi\PemakaianPelumasSheet;
use App\Services\Operasi\PersediaanSheet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The Operasi section of the dashboard, read from the Pengusahaan Operasi
 * sheets of the visible units (PRD.md §10.1): Stand kWh Harian (kWh produksi /
 * PS / netto), Beban Tertinggi, Tara Kalor, Pemakaian Bahan Bakar (SFC netto =
 * liter ÷ kWh netto), Jam Pembangkit, Persediaan Bahan Bakar, Pemakaian
 * Pelumas, Kinerja Unit Mesin (daya mampu) and Data Kinerja Pembangkit Termal
 * (EAF, EFOR, CF). Nothing is estimated: a sheet not filled counts as zero.
 */
class OperasiDashboard
{
    /** Performance targets of the gauges (percent). */
    public const TARGETS = ['eaf' => 90.0, 'efor' => 5.0, 'cf' => 60.0];

    public function __construct(
        private readonly KwhSheet $kwh,
        private readonly MesinHarianSheet $mesin,
        private readonly JamMesinSheet $jam,
        private readonly KinerjaMesinSheet $kinerja,
        private readonly KinerjaTermalSheet $termal,
        private readonly PersediaanSheet $persediaan,
        private readonly PemakaianPelumasSheet $pelumas,
    ) {}

    /**
     * @param  Collection<int, Unit>  $units
     * @param  list<array{code: string, name: string}>  $fuels  jenis BBM of the visible units (master)
     * @param  list<string>  $lubricants  jenis pelumas of the visible units (master)
     * @return array<string, mixed>
     */
    public function build(Collection $units, Carbon $now, array $fuels, array $lubricants): array
    {
        $month = $now->month;
        $year = $now->year;
        $previous = $now->copy()->startOfMonth()->subMonth();
        $days = $now->day;

        $current = [];
        $last = [];
        foreach ($units as $unit) {
            $current[$unit->id] = $this->energy($unit, $month, $year);
            $last[$unit->id] = $this->energy($unit, $previous->month, $previous->year);
        }

        $sumDays = function (string $field) use ($current, $days): array {
            $values = [];
            for ($day = 1; $day <= $days; $day++) {
                $values[] = round(array_sum(array_map(fn (array $unit): float => (float) ($unit[$field][$day] ?? 0), $current)) / 1000, 2);
            }

            return $values;
        };
        $nettDaily = $sumDays('nett_by_day');
        $psDaily = $sumDays('ps_by_day');
        $bebanDaily = $sumDays('beban_by_day');
        $prodDaily = $sumDays('produksi_by_day');

        $totals = $this->totals($current);
        $lastTotals = $this->totals($last);

        $performance = [];
        foreach ($units as $unit) {
            $performance[$unit->id] = $this->performance($unit, $month, $year);
        }
        $operating = array_filter($performance, fn (array $row): bool => $row['operating']);
        $average = fn (string $field): float => $operating === [] ? 0.0 : round(array_sum(array_column($operating, $field)) / count($operating), 2);

        return [
            'kpis' => [
                DashboardSupport::kpi('netto', 'Produksi kWh Netto', round($totals['nett'] / 1000, 2), 'MWh', 'decimal', 'up', round($lastTotals['nett'] / 1000, 2), $nettDaily),
                DashboardSupport::kpi('ps', 'Pemakaian Sendiri', $totals['ps_percent'], '%', 'decimal', 'down', $lastTotals['ps_percent'], array_map(fn (float $ps, float $prod): float => $prod > 0 ? round($ps / $prod * 100, 2) : 0.0, $psDaily, $prodDaily)),
                DashboardSupport::kpi('beban', 'Beban Tertinggi', round($totals['beban'] / 1000, 2), 'MW', 'decimal', 'up', round($lastTotals['beban'] / 1000, 2), $bebanDaily),
                DashboardSupport::kpi('sfc', 'SFC Netto', $totals['sfc'], 'L/kWh', 'decimal3', 'down', $lastTotals['sfc']),
                DashboardSupport::kpi('tara_kalor', 'Tara Kalor', $totals['tara_kalor'], 'kCal/kWh', 'number', 'down', $lastTotals['tara_kalor']),
            ],
            'daily' => [
                'labels' => array_map('strval', range(1, $days)),
                'series' => [
                    ['name' => 'kWh Netto (MWh)', 'values' => $nettDaily],
                    ['name' => 'Pemakaian Sendiri (MWh)', 'values' => $psDaily],
                    ['name' => 'Beban Tertinggi (MW)', 'values' => $bebanDaily],
                ],
            ],
            'jam_mesin' => $this->jamMesin($units, $month, $year),
            'gauges' => [
                ['label' => 'EAF', 'value' => $average('eaf'), 'target' => self::TARGETS['eaf'], 'good' => 'up'],
                ['label' => 'EFOR', 'value' => $average('efor'), 'target' => self::TARGETS['efor'], 'good' => 'down'],
                ['label' => 'CF', 'value' => $average('cf'), 'target' => self::TARGETS['cf'], 'good' => 'up'],
            ],
            'fuels' => $this->fuelStock($units, $month, $year, $fuels),
            'fuel_monthly' => $this->fuelMonthly($units, $now, $fuels),
            'pelumas' => $this->lubricants($units, $month, $year, $lubricants),
            'units' => $units->map(fn (Unit $unit): array => [
                'unit' => $unit->name,
                'daya_mampu' => $performance[$unit->id]['daya_mampu'],
                'beban_puncak' => round($current[$unit->id]['beban'] / 1000, 2),
                'produksi' => round($current[$unit->id]['nett'] / 1000, 2),
                'sfc' => $current[$unit->id]['nett'] > 0 ? round($current[$unit->id]['liter'] / $current[$unit->id]['nett'], 3) : 0.0,
                'tara_kalor' => $current[$unit->id]['tara_kalor'],
                'eaf' => $performance[$unit->id]['operating'] ? $performance[$unit->id]['eaf'] : null,
                'status' => $unit->status->label(),
                'status_tone' => $unit->status->tone(),
            ])->values()->all(),
        ];
    }

    /**
     * kWh produksi / PS / netto and beban tertinggi per day, tara kalor and
     * the liters of BBM of one unit in one month.
     *
     * @return array{produksi: float, ps: float, nett: float, beban: float, tara_kalor: float, liter: float, produksi_by_day: array<int, float>, ps_by_day: array<int, float>, nett_by_day: array<int, float>, beban_by_day: array<int, float>}
     */
    private function energy(Unit $unit, int $month, int $year): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $produksi = [];
        $ps = [];
        $nett = [];

        foreach ($this->kwh->harian($unit, $month, $year) as $sheet) {
            foreach ($sheet['rows'] as $row) {
                $produksi[$row['day']] = ($produksi[$row['day']] ?? 0) + (float) $row['produksi'];
                $ps[$row['day']] = ($ps[$row['day']] ?? 0) + (float) $row['ps'];
                $nett[$row['day']] = ($nett[$row['day']] ?? 0) + (float) $row['nett'];
            }
        }

        $machines = $this->mesin->machines($unit);
        $beban = $this->mesin->summarize(MesinHarianJenis::BebanTinggi, $this->mesin->bebanTertinggi($unit, $month, $year, $machines), $machines, $daysInMonth);
        $tara = $this->mesin->summarize(
            MesinHarianJenis::TaraKalor,
            $this->mesin->sanitize(MesinHarianJenis::TaraKalor, $this->mesin->record($unit, MesinHarianJenis::TaraKalor, $month, $year)?->readings ?? [], $machines, $daysInMonth),
            $machines,
            $daysInMonth,
        );

        return [
            'produksi' => (float) array_sum($produksi),
            'ps' => (float) array_sum($ps),
            'nett' => (float) array_sum($nett),
            'beban' => (float) $beban['total'],
            'tara_kalor' => round((float) $tara['total'], 2),
            'liter' => (float) (OperasiPemakaianBbm::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->value('grand_total_liter') ?? 0),
            'produksi_by_day' => $produksi,
            'ps_by_day' => $ps,
            'nett_by_day' => $nett,
            'beban_by_day' => $beban['by_day'],
        ];
    }

    /**
     * Totals over the units: kWh netto, PS share of produksi, the highest
     * daily beban (sum of the units), SFC netto and the average tara kalor of
     * the units that filled it.
     *
     * @param  array<int, array<string, mixed>>  $byUnit
     * @return array{nett: float, ps_percent: float, beban: float, sfc: float, tara_kalor: float}
     */
    private function totals(array $byUnit): array
    {
        $produksi = (float) array_sum(array_column($byUnit, 'produksi'));
        $nett = (float) array_sum(array_column($byUnit, 'nett'));
        $liter = (float) array_sum(array_column($byUnit, 'liter'));
        $tara = array_filter(array_column($byUnit, 'tara_kalor'), fn (float $value): bool => $value > 0);
        $beban = [];
        foreach ($byUnit as $unit) {
            foreach ($unit['beban_by_day'] as $day => $value) {
                $beban[$day] = ($beban[$day] ?? 0) + $value;
            }
        }

        return [
            'nett' => $nett,
            'ps_percent' => $produksi > 0 ? round(array_sum(array_column($byUnit, 'ps')) / $produksi * 100, 2) : 0.0,
            'beban' => (float) max([0, ...array_values($beban)]),
            'sfc' => $nett > 0 ? round($liter / $nett, 3) : 0.0,
            'tara_kalor' => $tara === [] ? 0.0 : round(array_sum($tara) / count($tara), 2),
        ];
    }

    /**
     * Daya mampu (MW) and the EAF / EFOR / CF averages of one unit; a unit
     * without jam operasi this month is left out of the averages.
     *
     * @return array{operating: bool, daya_mampu: float, eaf: float, efor: float, cf: float}
     */
    private function performance(Unit $unit, int $month, int $year): array
    {
        $termal = $this->termal->build($unit, $month, $year);
        $operating = collect($termal['rows'])->contains(fn (array $row): bool => $row['operating'] && $row['source']['jam_operasi'] > 0);

        return [
            'operating' => $operating,
            'daya_mampu' => round((float) $this->kinerja->build($unit, $month, $year)['totals']['daya_mampu'] / 1000, 2),
            'eaf' => (float) ($termal['averages']['eaf'] ?? 0),
            'efor' => (float) ($termal['averages']['efor'] ?? 0),
            'cf' => (float) ($termal['averages']['cf'] ?? 0),
        ];
    }

    /**
     * Hours of the Jam Pembangkit sheets of every machine this month.
     *
     * @param  Collection<int, Unit>  $units
     * @return list<array{label: string, value: float}>
     */
    private function jamMesin(Collection $units, int $month, int $year): array
    {
        $labels = [
            JamMesinJenis::Operasi->value => 'Operasi',
            JamMesinJenis::Standby->value => 'Standby',
            JamMesinJenis::Pemeliharaan->value => 'Pemeliharaan (PO)',
            JamMesinJenis::MaintenanceOutage->value => 'MO',
            JamMesinJenis::Gangguan->value => 'Gangguan',
        ];
        $hours = array_fill_keys(array_keys($labels), 0.0);

        foreach ($units as $unit) {
            $machines = $this->jam->machines($unit);
            foreach (JamMesinJenis::cases() as $jenis) {
                $hours[$jenis->value] += (float) $this->jam->summarize($this->jam->readings($unit, $jenis, $month, $year, $machines), $machines)['grand_total'];
            }
        }

        return array_map(fn (string $key): array => ['label' => $labels[$key], 'value' => round($hours[$key], 2)], array_keys($labels));
    }

    /**
     * Persediaan Bahan Bakar of the month per jenis BBM (liter): opening,
     * penerimaan, pemakaian, pengiriman and saldo akhir.
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<array{code: string, name: string}>  $fuels
     * @return list<array{code: string, name: string, awal: float, penerimaan: float, pemakaian: float, pengiriman: float, sisa: float}>
     */
    private function fuelStock(Collection $units, int $month, int $year, array $fuels): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $kirim = array_keys($this->persediaan->kirimColumns(PersediaanJenis::Bbm));
        $empty = fn (string $code, string $name): array => ['code' => $code, 'name' => $name, 'awal' => 0.0, 'penerimaan' => 0.0, 'pemakaian' => 0.0, 'pengiriman' => 0.0, 'sisa' => 0.0];
        $rows = [];
        foreach ($fuels as $fuel) {
            $rows[$fuel['code']] = $empty($fuel['code'], $fuel['name']);
        }

        foreach ($units as $unit) {
            $items = $this->persediaan->items($unit, PersediaanJenis::Bbm);
            if ($items === []) {
                continue;
            }

            $record = $this->persediaan->record($unit, PersediaanJenis::Bbm, $month, $year);
            $opening = $this->persediaan->openingOf($record, $this->persediaan->carriedOpening($unit, PersediaanJenis::Bbm, $month, $year, $items), $items);
            $sheet = $this->persediaan->compute(PersediaanJenis::Bbm, $items, $opening, $record?->entries ?? [], $this->persediaan->pemakaian($unit, PersediaanJenis::Bbm, $month, $year, $items), $daysInMonth);

            foreach ($sheet as $code => $block) {
                $code = (string) $code;
                $rows[$code] ??= $empty($code, $code);
                $rows[$code]['awal'] += $block['opening'];
                $rows[$code]['penerimaan'] += (float) ($block['total']['penerimaan'] ?? 0);
                $rows[$code]['pemakaian'] += (float) ($block['total']['pemakaian'] ?? 0);
                $rows[$code]['pengiriman'] += array_sum(array_map(fn (string $field): float => (float) ($block['total'][$field] ?? 0), $kirim));
                $rows[$code]['sisa'] += (float) $block['total']['akhir'];
            }
        }

        return array_values(array_map(fn (array $row): array => [
            ...$row,
            'awal' => round($row['awal'], 2),
            'penerimaan' => round($row['penerimaan'], 2),
            'pemakaian' => round($row['pemakaian'], 2),
            'pengiriman' => round($row['pengiriman'], 2),
            'sisa' => round($row['sisa'], 2),
        ], $rows));
    }

    /**
     * Pemakaian Bahan Bakar per jenis BBM over the last six months (kL).
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<array{code: string, name: string}>  $fuels
     * @return array{labels: list<string>, series: list<array{name: string, values: list<float>}>}
     */
    private function fuelMonthly(Collection $units, Carbon $now, array $fuels): array
    {
        $months = DashboardSupport::months($now);
        $first = $months[0];
        $byMonth = [];

        OperasiPemakaianBbm::query()
            ->whereIn('unit_id', $units->pluck('id'))
            ->where(fn ($query) => $query->where('year', '>', $first['year'])->orWhere(fn ($q) => $q->where('year', $first['year'])->where('month', '>=', $first['month'])))
            ->get(['month', 'year', 'totals_by_fuel'])
            ->each(function (OperasiPemakaianBbm $record) use (&$byMonth): void {
                $key = DashboardSupport::key((int) $record->year, (int) $record->month);
                foreach ((array) $record->totals_by_fuel as $code => $liter) {
                    $byMonth[$key][$code] = ($byMonth[$key][$code] ?? 0) + (float) $liter;
                }
            });

        return [
            'labels' => array_column($months, 'label'),
            'series' => array_map(fn (array $fuel): array => [
                'name' => "{$fuel['code']} (kL)",
                'values' => array_map(fn (array $m): float => round(($byMonth[DashboardSupport::key($m['year'], $m['month'])][$fuel['code']] ?? 0) / 1000, 2), $months),
            ], $fuels),
        ];
    }

    /**
     * Pelumas tambah and ganti of the month per jenis pelumas (liter), every
     * jenis of the master listed even when unused.
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<string>  $lubricants
     * @return list<array{label: string, tambah: float, ganti: float}>
     */
    private function lubricants(Collection $units, int $month, int $year, array $lubricants): array
    {
        $rows = [];
        foreach ($lubricants as $name) {
            $rows[$name] = ['label' => $name, 'tambah' => 0.0, 'ganti' => 0.0];
        }

        foreach ($units as $unit) {
            $sheet = $this->pelumas->tambahGanti($unit, $month, $year);
            $names = array_column($sheet['lubricants'], 'name', 'id');

            foreach (['tambah', 'ganti'] as $field) {
                foreach ($sheet[$field] as $key => $dayValues) {
                    $name = $names[(int) explode('_', (string) $key)[0]] ?? null;
                    if ($name === null) {
                        continue;
                    }
                    $rows[$name] ??= ['label' => $name, 'tambah' => 0.0, 'ganti' => 0.0];
                    $rows[$name][$field] += array_sum(array_map('floatval', $dayValues));
                }
            }
        }

        return array_values(array_map(fn (array $row): array => [...$row, 'tambah' => round($row['tambah'], 2), 'ganti' => round($row['ganti'], 2)], $rows));
    }
}
