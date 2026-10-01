<?php

namespace App\Services\Operasi;

use App\Enums\BeritaAcaraType;
use App\Enums\StatusCodeCategory;
use App\Models\AuxiliarySource;
use App\Models\DailyAuxiliaryReading;
use App\Models\DailyFeederReading;
use App\Models\DocumentRecord;
use App\Models\EngineStatusLog;
use App\Models\Feeder;
use App\Models\FuelReceipt;
use App\Models\LubricantReceipt;
use App\Models\Machine;
use App\Models\OperasiResourcePembangkit;
use App\Models\Unit;
use App\Services\K3\K3PengusahaanReport;
use Illuminate\Support\Carbon;

/**
 * The content of the Laporan Pengusahaan Pembangkit (Operasi): every Akses 2 —
 * Pengusahaan Operasi input of a unit and month (pages/pengusahaan/operasi —
 * Input Harian, Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM, Resource
 * Pembangkit, Berita Acara), each as a generic table section in the same shape
 * as {@see K3PengusahaanReport}, so the shared section partial
 * and spreadsheet grid render it. Production, own use and fuel come from
 * {@see OperasiCalculator} (the same numbers as the input page); hours from the
 * Star-Stop log.
 *
 * Section shape: key, no, title, number, orientation, meta (label => value),
 * head & rows (cells {t, c colspan, r rowspan, w width, a l|c|r, b bold, s
 * section row, i italic}), filled and note.
 */
class OperasiPengusahaanReport
{
    private int $days = 31;

    private string $number = '';

    public function __construct(private readonly OperasiCalculator $calculator) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(Unit $unit, int $month, int $year): array
    {
        $this->days = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $this->number = self::documentNumber($unit, $month, $year);

        $engines = $this->engines($unit, $month, $year);

        $sections = [
            $this->kinerja($unit, $month, $year, $engines),
            $this->harian($engines),
            $this->resource($unit, $month, $year),
            $this->beritaAcara($unit, $month, $year),
            $this->pelumas($unit, $month, $year),
            ...$this->meters($unit, $month, $year),
            $this->perMesin($engines),
            $this->starStop($unit, $month, $year),
            $this->penerimaanBbm($unit, $month, $year),
        ];

        // Numbered in reading order: the PDF prints the portrait pages before the landscape ones.
        $sections = [
            ...array_filter($sections, fn (array $s): bool => $s['orientation'] === 'portrait'),
            ...array_filter($sections, fn (array $s): bool => $s['orientation'] === 'landscape'),
        ];
        foreach ($sections as $index => &$section) {
            $section['no'] = $index + 2;
        }
        unset($section);

        return [$this->recap($sections), ...$sections];
    }

    public static function documentNumber(Unit $unit, int $month, int $year): string
    {
        return sprintf('LAP-PENG-OPS/%s/%02d-%d', $unit->code ?? $unit->id, $month, $year);
    }

    /**
     * Each machine's daily grid (OperasiCalculator) and Star-Stop hours.
     *
     * @return list<array{engine: Machine, grid: array{rows: list<array<string, mixed>>, summary: array<string, array<string, float>>}, hours: array{operasi: float, har: float, gangguan: float, standby: float, total: float}, filled: int}>
     */
    private function engines(Unit $unit, int $month, int $year): array
    {
        return Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get()
            ->map(function (Machine $engine) use ($month, $year): array {
                $grid = $this->calculator->buildDailyEngineGrid($engine, $month, $year);

                return [
                    'engine' => $engine,
                    'grid' => $grid,
                    'hours' => $this->calculator->hoursSummary($engine, $month, $year),
                    'filled' => count(array_filter($grid['rows'], fn (array $row): bool => (bool) $row['is_complete'])),
                ];
            })->all();
    }

    /**
     * Section 1: which pengusahaan inputs were filled this month.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function recap(array $sections): array
    {
        $rows = array_map(fn (array $s): array => [
            $this->cell((string) $s['no'], 'c'),
            $this->cell($s['title']),
            $this->cell($s['source'] ?? '-', 'c'),
            $this->cell($s['filled'] ? 'Terisi' : 'Belum diisi', 'c', true),
        ], $sections);

        return ['no' => 1] + $this->section('rekap', 'Rekapitulasi Pengisian Input Pengusahaan Operasi', 'portrait', [[
            $this->h('No', w: '6%'), $this->h('Bagian Laporan'), $this->h('Sumber Input (Pengusahaan Operasi)', w: '30%'), $this->h('Status', w: '14%'),
        ]], $rows, true, source: 'Rekap');
    }

    /**
     * Ringkasan Kinerja Pengusahaan: the month's key figures of the unit.
     *
     * @param  list<array<string, mixed>>  $engines
     * @return array<string, mixed>
     */
    private function kinerja(Unit $unit, int $month, int $year, array $engines): array
    {
        $total = fn (string $field): float => array_sum(array_map(fn (array $e): float => (float) ($e['grid']['summary']['total'][$field] ?? 0), $engines));
        $hours = fn (string $field): float => array_sum(array_map(fn (array $e): float => (float) $e['hours'][$field], $engines));
        $produksi = $total('kwh_produksi');
        $ps = $total('kwh_pakai_sendiri');
        $hsd = $total('pemakaian_hsd');
        $mfo = $total('pemakaian_mfo');
        $sfc = $this->calculator->sfc($hsd + $mfo, $produksi, $ps);
        [$peak, $peakDay] = $this->unitPeak($engines);

        $receipts = FuelReceipt::query()->where('unit_id', $unit->id)->whereYear('report_date', $year)->whereMonth('report_date', $month)->get();
        $resource = OperasiResourcePembangkit::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->orderByDesc('tanggal')->first();
        $gangguan = EngineStatusLog::query()->where('unit_id', $unit->id)->whereYear('report_date', $year)->whereMonth('report_date', $month)
            ->whereHas('statusCode', fn ($q) => $q->where('category', StatusCodeCategory::Gangguan->value))->count();
        $feeders = $this->meterTotals(DailyFeederReading::class, 'feeder_id', 'stand_akhir', $unit, $month, $year);
        $auxKwh = $this->meterTotals(DailyAuxiliaryReading::class, 'auxiliary_source_id', 'stand_kwh_akhir', $unit, $month, $year);

        $lines = [
            ['A. Produksi Energi', null, null],
            ['Produksi kWh bruto', 'kWh', $this->num($produksi)],
            ['Pemakaian sendiri (PS)', 'kWh', $this->num($ps)],
            ['Produksi kWh netto', 'kWh', $this->num($produksi - $ps)],
            ['Persentase pemakaian sendiri', '%', $produksi > 0 ? $this->num($ps / $produksi * 100, 2) : '-'],
            ['Energi tersalur ke feeder', 'kWh', $this->num($feeders)],
            ['Pasokan cadangan (auxiliary)', 'kWh', $this->num($auxKwh)],
            ['Beban puncak tertinggi', 'kW', $peak > 0 ? $this->num($peak).($peakDay ? " (tgl {$peakDay})" : '') : '-'],
            ['B. Bahan Bakar & Pelumas', null, null],
            ['Pemakaian BBM HSD', 'Liter', $this->num($hsd)],
            ['Pemakaian BBM MFO', 'Liter', $this->num($mfo)],
            ['SFC bruto', 'L/kWh', $sfc['bruto'] === null ? '-' : $this->num($sfc['bruto'], 4)],
            ['SFC netto', 'L/kWh', $sfc['netto'] === null ? '-' : $this->num($sfc['netto'], 4)],
            ['Pemakaian pelumas', 'Liter', $this->num($total('pemakaian_pelumas_liter'))],
            ['Penerimaan BBM HSD', 'Liter', $this->num((float) $receipts->where('fuel_type.value', 'hsd')->sum('volume_liter'))],
            ['Penerimaan BBM MFO', 'Liter', $this->num((float) $receipts->where('fuel_type.value', 'mfo')->sum('volume_liter'))],
            ['Stok BBM akhir bulan (Resource Pembangkit)', 'Liter', $resource === null ? '-' : $this->num((float) $resource->stok_akhir)],
            ['C. Kesiapan Mesin', null, null],
            ['Jumlah mesin aktif', 'Unit', (string) count($engines)],
            ['Jam operasi (seluruh mesin)', 'Jam', $this->num($hours('operasi'), 2)],
            ['Jam pemeliharaan (HAR)', 'Jam', $this->num($hours('har'), 2)],
            ['Jam gangguan', 'Jam', $this->num($hours('gangguan'), 2)],
            ['Jumlah kejadian gangguan (Star-Stop)', 'Kali', (string) $gangguan],
            ['Hari input harian lengkap', 'Hari', count($engines) === 0 ? '-' : min(array_column($engines, 'filled')).' / '.$this->days.' (mesin paling sedikit)'],
        ];

        $rows = [];
        $no = 0;
        foreach ($lines as [$label, $unitLabel, $value]) {
            if ($unitLabel === null) {
                $rows[] = $this->sectionRow($label, 4);

                continue;
            }
            $rows[] = [$this->cell((string) ++$no, 'c'), $this->cell($label), $this->cell($unitLabel, 'c'), $this->cell($value, 'r', true)];
        }

        $filled = $produksi > 0 || $receipts->isNotEmpty() || $resource !== null;

        return $this->section('kinerja', 'Ringkasan Kinerja Pengusahaan Pembangkit', 'portrait', [[
            $this->h('No', w: '6%'), $this->h('Uraian'), $this->h('Satuan', w: '14%'), $this->h('Realisasi', w: '24%'),
        ]], $rows, $filled, 'Dihitung otomatis dari Input Harian (stand meter & faktor kalibrasi), Star-Stop, Feeder, Pasokan Cadangan, Penerimaan BBM dan Resource Pembangkit.', source: 'Seluruh input');
    }

    /**
     * Highest unit peak load of the month: per day the sum of every machine's
     * pagi / malam peak, the larger of the two.
     *
     * @param  list<array<string, mixed>>  $engines
     * @return array{0: float, 1: int|null}
     */
    private function unitPeak(array $engines): array
    {
        $best = 0.0;
        $bestDay = null;
        foreach (range(1, $this->days) as $day) {
            $pagi = 0.0;
            $malam = 0.0;
            foreach ($engines as $engine) {
                $row = $engine['grid']['rows'][$day - 1] ?? [];
                $pagi += (float) ($row['beban_puncak_pagi_kw'] ?? 0);
                $malam += (float) ($row['beban_puncak_malam_kw'] ?? 0);
            }
            if (max($pagi, $malam) > $best) {
                $best = max($pagi, $malam);
                $bestDay = $day;
            }
        }

        return [$best, $bestDay];
    }

    /**
     * Laporan Harian Produksi Unit: the daily totals of every machine.
     *
     * @param  list<array<string, mixed>>  $engines
     * @return array<string, mixed>
     */
    private function harian(array $engines): array
    {
        $fields = ['kwh_produksi', 'kwh_pakai_sendiri', 'kwh_netto', 'pemakaian_hsd', 'pemakaian_mfo', 'pemakaian_pelumas_liter', 'beban_puncak_pagi_kw', 'beban_puncak_malam_kw'];
        $totals = array_fill_keys($fields, 0.0);
        $rows = [];

        foreach (range(1, $this->days) as $day) {
            $line = [$this->cell((string) $day, 'c')];
            foreach ($fields as $field) {
                $value = array_sum(array_map(fn (array $e): float => (float) (($e['grid']['rows'][$day - 1] ?? [])[$field] ?? 0), $engines));
                $line[] = $this->cell($this->num($value), 'r');
                $totals[$field] = str_starts_with($field, 'beban') ? max($totals[$field], $value) : $totals[$field] + $value;
            }
            $rows[] = $line;
        }

        $rows[] = [$this->cell('Jumlah / Maks', 'c', true), ...array_map(fn (string $f): array => $this->cell($this->num($totals[$f]), 'r', true), $fields)];
        $filled = array_sum(array_column($engines, 'filled')) > 0;

        return $this->section('harian', 'Laporan Harian Produksi & Pemakaian Bahan Bakar Unit', 'portrait', [
            [$this->h('Tgl', r: 2, w: '6%'), $this->h('kWh', c: 3), $this->h('BBM (Liter)', c: 2), $this->h('Pelumas (L)', r: 2), $this->h('Beban Puncak (kW)', c: 2)],
            [$this->h('Produksi'), $this->h('PS'), $this->h('Netto'), $this->h('HSD'), $this->h('MFO'), $this->h('Pagi'), $this->h('Malam')],
        ], $filled ? $rows : [], $filled, 'Jumlah seluruh mesin per hari; baris terakhir: jumlah bulan (beban puncak: nilai tertinggi).', source: 'Input Harian');
    }

    /**
     * Produksi, bahan bakar & jam per mesin.
     *
     * @param  list<array<string, mixed>>  $engines
     * @return array<string, mixed>
     */
    private function perMesin(array $engines): array
    {
        $rows = [];
        foreach ($engines as $i => $engine) {
            $sum = $engine['grid']['summary']['total'];
            $sfc = $this->calculator->sfc((float) $sum['pemakaian_hsd'] + (float) $sum['pemakaian_mfo'], (float) $sum['kwh_produksi'], (float) $sum['kwh_pakai_sendiri']);
            $peak = max(array_map(fn (array $r): float => max((float) ($r['beban_puncak_pagi_kw'] ?? 0), (float) ($r['beban_puncak_malam_kw'] ?? 0)), $engine['grid']['rows']) ?: [0]);
            $rows[] = [
                $this->cell((string) ($i + 1), 'c'),
                $this->cell($engine['engine']->name),
                $this->cell($this->num($sum['kwh_produksi']), 'r'),
                $this->cell($this->num($sum['kwh_pakai_sendiri']), 'r'),
                $this->cell($this->num($sum['kwh_netto']), 'r'),
                $this->cell($this->num($sum['pemakaian_hsd']), 'r'),
                $this->cell($this->num($sum['pemakaian_mfo']), 'r'),
                $this->cell($this->num($sum['pemakaian_pelumas_liter']), 'r'),
                $this->cell($sfc['bruto'] === null ? '-' : $this->num($sfc['bruto'], 4), 'r'),
                $this->cell($this->num($peak), 'r'),
                $this->cell($this->num($engine['hours']['operasi'], 2), 'r'),
                $this->cell($this->num($engine['hours']['har'], 2), 'r'),
                $this->cell($this->num($engine['hours']['gangguan'], 2), 'r'),
                $this->cell($this->num($engine['hours']['standby'], 2), 'r'),
                $this->cell($engine['filled'].' / '.$this->days, 'c'),
            ];
        }

        return $this->section('per-mesin', 'Produksi, Bahan Bakar & Jam Operasi per Mesin', 'landscape', [
            [$this->h('No', r: 2, w: '3%'), $this->h('Mesin', r: 2), $this->h('kWh', c: 3), $this->h('BBM (Liter)', c: 2), $this->h('Pelumas (L)', r: 2), $this->h('SFC Bruto (L/kWh)', r: 2), $this->h('Beban Puncak (kW)', r: 2), $this->h('Jam (Star-Stop)', c: 4), $this->h('Hari Terisi', r: 2)],
            [$this->h('Produksi'), $this->h('PS'), $this->h('Netto'), $this->h('HSD'), $this->h('MFO'), $this->h('Operasi'), $this->h('HAR'), $this->h('Gangguan'), $this->h('Standby')],
        ], $rows, array_sum(array_column($engines, 'filled')) > 0, source: 'Input Harian & Star-Stop');
    }

    /** @return array<string, mixed> */
    private function starStop(Unit $unit, int $month, int $year): array
    {
        $logs = EngineStatusLog::query()->with(['engine:id,name', 'statusCode'])
            ->where('unit_id', $unit->id)->whereYear('report_date', $year)->whereMonth('report_date', $month)
            ->orderBy('report_date')->orderBy('start_datetime')->get();

        $rows = $logs->values()->map(fn (EngineStatusLog $log, int $i): array => [
            $this->cell((string) ($i + 1), 'c'),
            $this->cell($log->report_date->format('d/m/Y'), 'c'),
            $this->cell((string) ($log->engine?->name ?? '-')),
            $this->cell(trim(($log->statusCode?->code ?? '').' — '.($log->statusCode?->label ?? ''), ' —')),
            $this->cell($log->statusCode?->category?->label() ?? '-', 'c'),
            $this->cell($log->start_datetime?->format('d/m H:i') ?? '-', 'c'),
            $this->cell($log->stop_datetime?->format('d/m H:i') ?? '-', 'c'),
            $this->cell($log->duration_minutes === null ? '-' : $this->num($log->duration_minutes / 60, 2), 'r'),
            $this->cell((string) ($log->operator_name ?? '')),
            $this->cell((string) ($log->dispatcher_name ?? '')),
            $this->cell((string) ($log->keterangan ?? '')),
        ])->all();

        return $this->section('star-stop', 'Laporan Star-Stop Mesin Pembangkit', 'landscape', [[
            $this->h('No', w: '3%'), $this->h('Tanggal', w: '8%'), $this->h('Mesin', w: '10%'), $this->h('Status'), $this->h('Kategori', w: '8%'),
            $this->h('Start', w: '8%'), $this->h('Stop', w: '8%'), $this->h('Durasi (Jam)', w: '7%'), $this->h('Operator', w: '10%'), $this->h('Dispatcher', w: '10%'), $this->h('Keterangan'),
        ]], $rows, $logs->isNotEmpty(), source: 'Star-Stop Mesin');
    }

    /**
     * Feeder and Pasokan Cadangan: the daily stand readings and the month's
     * delivered / supplied energy (last stand − last stand before the month).
     *
     * @return list<array<string, mixed>>
     */
    private function meters(Unit $unit, int $month, int $year): array
    {
        $feeders = Feeder::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $sources = AuxiliarySource::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        $feederColumns = $feeders->map(fn (Feeder $f): array => ['label' => $f->name, 'id' => $f->id, 'field' => 'stand_akhir'])->all();
        $auxColumns = $sources->flatMap(fn (AuxiliarySource $s): array => [
            ['label' => "{$s->name} · kWh", 'id' => $s->id, 'field' => 'stand_kwh_akhir'],
            ['label' => "{$s->name} · BBM", 'id' => $s->id, 'field' => 'stand_bbm_akhir'],
        ])->all();

        return [
            $this->meterSection('feeder', 'Pembacaan kWh Penyaluran Feeder', DailyFeederReading::class, 'feeder_id', $feederColumns, $unit, $month, $year, 'Feeder', 'kWh tersalur'),
            $this->meterSection('pasokan-cadangan', 'Pembacaan Pasokan Cadangan (Auxiliary)', DailyAuxiliaryReading::class, 'auxiliary_source_id', $auxColumns, $unit, $month, $year, 'Pasokan Cadangan', 'Pemakaian bulan ini'),
        ];
    }

    /**
     * @param  class-string<DailyFeederReading|DailyAuxiliaryReading>  $model
     * @param  list<array{label: string, id: int, field: string}>  $columns
     * @return array<string, mixed>
     */
    private function meterSection(string $key, string $title, string $model, string $foreignKey, array $columns, Unit $unit, int $month, int $year, string $source, string $deltaLabel): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $readings = $model::query()->where('unit_id', $unit->id)->whereYear('report_date', $year)->whereMonth('report_date', $month)->get()
            ->groupBy(fn ($r): string => $r->{$foreignKey}.'|'.$r->report_date->day);

        $rows = [];
        foreach (range(1, $this->days) as $day) {
            $line = [$this->cell((string) $day, 'c')];
            foreach ($columns as $column) {
                $value = $readings->get($column['id'].'|'.$day)?->first()?->{$column['field']};
                $line[] = $this->cell($value === null || $value === '' ? '' : $this->num((float) $value), 'r');
            }
            $rows[] = $line;
        }

        $summary = fn (string $label, callable $value): array => [$this->cell($label, 'l', true), ...array_map(fn (array $c): array => $this->cell($value($c), 'r', true), $columns)];
        $opening = fn (array $c): ?float => $this->toFloat($model::query()->where($foreignKey, $c['id'])->where('report_date', '<', $start->toDateString())->whereNotNull($c['field'])->orderByDesc('report_date')->value($c['field']));
        $closing = fn (array $c): ?float => $this->toFloat($model::query()->where($foreignKey, $c['id'])->whereYear('report_date', $year)->whereMonth('report_date', $month)->whereNotNull($c['field'])->orderByDesc('report_date')->value($c['field']));
        $firstOfMonth = fn (array $c): ?float => $this->toFloat($model::query()->where($foreignKey, $c['id'])->whereYear('report_date', $year)->whereMonth('report_date', $month)->whereNotNull($c['field'])->orderBy('report_date')->value($c['field']));

        $rows[] = $summary('Stand awal', fn (array $c): string => ($v = $opening($c) ?? $firstOfMonth($c)) === null ? '-' : $this->num($v));
        $rows[] = $summary('Stand akhir', fn (array $c): string => ($v = $closing($c)) === null ? '-' : $this->num($v));
        $rows[] = $summary($deltaLabel, function (array $c) use ($opening, $closing, $firstOfMonth): string {
            $awal = $opening($c) ?? $firstOfMonth($c);
            $akhir = $closing($c);

            return $awal === null || $akhir === null ? '-' : $this->num($akhir - $awal);
        });

        $orientation = count($columns) > 6 ? 'landscape' : 'portrait';
        $filled = $readings->isNotEmpty();

        return $this->section($key, $title, $orientation, [[
            $this->h('Tgl', w: '8%'), ...array_map(fn (array $c): array => $this->h($c['label']), $columns),
        ]], $columns === [] || ! $filled ? [] : $rows, $filled,
            $columns === [] ? "Belum ada master {$source} aktif untuk unit ini." : 'Nilai = stand meter akhir hari; selisih = stand akhir bulan − stand akhir bulan sebelumnya.',
            source: $source);
    }

    /**
     * The month's delivered / supplied kWh over every meter of a kind.
     *
     * @param  class-string<DailyFeederReading|DailyAuxiliaryReading>  $model
     */
    private function meterTotals(string $model, string $foreignKey, string $field, Unit $unit, int $month, int $year): float
    {
        $start = Carbon::create($year, $month, 1)->startOfDay()->toDateString();
        $ids = $model::query()->where('unit_id', $unit->id)->whereYear('report_date', $year)->whereMonth('report_date', $month)->distinct()->pluck($foreignKey);

        return (float) $ids->sum(function (int $id) use ($model, $foreignKey, $field, $start, $month, $year): float {
            $inMonth = $model::query()->where($foreignKey, $id)->whereYear('report_date', $year)->whereMonth('report_date', $month)->whereNotNull($field);
            $akhir = $this->toFloat((clone $inMonth)->orderByDesc('report_date')->value($field));
            $awal = $this->toFloat($model::query()->where($foreignKey, $id)->where('report_date', '<', $start)->whereNotNull($field)->orderByDesc('report_date')->value($field))
                ?? $this->toFloat((clone $inMonth)->orderBy('report_date')->value($field));

            return $akhir === null || $awal === null ? 0.0 : max(0.0, $akhir - $awal);
        });
    }

    /** @return array<string, mixed> */
    private function penerimaanBbm(Unit $unit, int $month, int $year): array
    {
        $receipts = FuelReceipt::query()->where('unit_id', $unit->id)->whereYear('report_date', $year)->whereMonth('report_date', $month)->orderBy('report_date')->get();

        $rows = $receipts->values()->map(fn (FuelReceipt $r, int $i): array => [
            $this->cell((string) ($i + 1), 'c'),
            $this->cell($r->report_date->format('d/m/Y'), 'c'),
            $this->cell($r->fuel_type->label(), 'c'),
            $this->cell((string) ($r->supplier ?? '')),
            $this->cell((string) ($r->do_number ?? ''), 'c'),
            $this->cell($r->unloading_date?->format('d/m/Y') ?? '-', 'c'),
            $this->cell($this->num((float) $r->volume_liter), 'r'),
            $this->cell($r->calorie_value === null ? '-' : $this->num((float) $r->calorie_value, 2), 'r'),
            $this->cell($r->price_per_liter === null ? '-' : $this->num((float) $r->price_per_liter, 2), 'r'),
            $this->cell($r->transport_cost === null ? '-' : $this->num((float) $r->transport_cost), 'r'),
            $this->cell($r->surveyor_cost === null ? '-' : $this->num((float) $r->surveyor_cost), 'r'),
            $this->cell((string) ($r->keterangan ?? '')),
        ])->all();

        if ($rows !== []) {
            foreach ($receipts->groupBy(fn (FuelReceipt $r): string => $r->fuel_type->label()) as $label => $group) {
                $rows[] = [['t' => "Jumlah {$label}", 'c' => 6, 'a' => 'r', 'b' => true], $this->cell($this->num((float) $group->sum('volume_liter')), 'r', true), ['t' => '', 'c' => 5]];
            }
        }

        return $this->section('penerimaan-bbm', 'Laporan Penerimaan Bahan Bakar Minyak', 'landscape', [[
            $this->h('No', w: '3%'), $this->h('Tanggal', w: '8%'), $this->h('Jenis', w: '6%'), $this->h('Supplier'), $this->h('No. DO', w: '10%'), $this->h('Tgl Bongkar', w: '8%'),
            $this->h('Volume (L)', w: '9%'), $this->h('Nilai Kalori', w: '7%'), $this->h('Harga / L', w: '8%'), $this->h('Biaya Transport', w: '9%'), $this->h('Biaya Surveyor', w: '9%'), $this->h('Keterangan'),
        ]], $rows, $receipts->isNotEmpty(), source: 'Penerimaan BBM');
    }

    /** @return array<string, mixed> */
    private function pelumas(Unit $unit, int $month, int $year): array
    {
        $receipts = LubricantReceipt::query()->with('lubricantType')->where('unit_id', $unit->id)->whereYear('report_date', $year)->whereMonth('report_date', $month)->orderBy('report_date')->get();

        $rows = $receipts->values()->map(fn (LubricantReceipt $r, int $i): array => [
            $this->cell((string) ($i + 1), 'c'),
            $this->cell($r->report_date->format('d/m/Y'), 'c'),
            $this->cell((string) ($r->lubricantType?->name ?? '-')),
            $this->cell((string) ($r->do_number ?? ''), 'c'),
            $this->cell($this->num((float) $r->volume), 'r'),
        ])->all();

        return $this->section('penerimaan-pelumas', 'Laporan Penerimaan Pelumas', 'portrait', [[
            $this->h('No', w: '6%'), $this->h('Tanggal', w: '16%'), $this->h('Jenis Pelumas'), $this->h('No. DO', w: '20%'), $this->h('Volume', w: '16%'),
        ]], $rows, $receipts->isNotEmpty(), source: 'Penerimaan BBM');
    }

    /** @return array<string, mixed> */
    private function resource(Unit $unit, int $month, int $year): array
    {
        $items = OperasiResourcePembangkit::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->orderBy('tanggal')->get();

        $rows = $items->map(fn (OperasiResourcePembangkit $r): array => [
            $this->cell((string) $r->tanggal, 'c'),
            $this->cell($this->num((float) $r->stok_awal), 'r'),
            $this->cell($this->num((float) $r->pemakaian), 'r'),
            $this->cell($this->num((float) $r->pengiriman), 'r'),
            $this->cell($this->num((float) $r->stok_akhir), 'r'),
            $this->cell((string) ($r->keterangan ?? '')),
        ])->all();

        if ($rows !== []) {
            $rows[] = [
                $this->cell('Jumlah', 'c', true),
                $this->cell($this->num((float) $items->first()->stok_awal), 'r', true),
                $this->cell($this->num((float) $items->sum('pemakaian')), 'r', true),
                $this->cell($this->num((float) $items->sum('pengiriman')), 'r', true),
                $this->cell($this->num((float) $items->last()->stok_akhir), 'r', true),
                $this->cell('Stok awal = tgl pertama; stok akhir = tgl terakhir'),
            ];
        }

        return $this->section('resource-pembangkit', 'Laporan Resource Pembangkit (Stok BBM Harian)', 'portrait', [[
            $this->h('Tgl', w: '8%'), $this->h('Stok Awal (L)'), $this->h('Pemakaian (L)'), $this->h('Pengiriman (L)'), $this->h('Stok Akhir (L)'), $this->h('Keterangan', w: '26%'),
        ]], $rows, $items->isNotEmpty(), source: 'Resource Pembangkit');
    }

    /** @return array<string, mixed> */
    private function beritaAcara(Unit $unit, int $month, int $year): array
    {
        $records = DocumentRecord::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->get()->keyBy(fn (DocumentRecord $r): string => $r->type->value);

        $rows = array_map(function (BeritaAcaraType $type, int $i) use ($records): array {
            $record = $records->get($type->value);

            return [
                $this->cell((string) ($i + 1), 'c'),
                $this->cell($type->label()),
                $this->cell($record?->document_number ?? '-', 'c'),
                $this->cell($record === null ? 'Belum dibuat' : ($record->approved_at !== null ? 'Disetujui' : 'Dibuat'), 'c', true),
                $this->cell($record?->approved_at?->format('d/m/Y') ?? '-', 'c'),
            ];
        }, BeritaAcaraType::cases(), array_keys(BeritaAcaraType::cases()));

        return $this->section('berita-acara', 'Status Berita Acara BBM & Pelumas', 'portrait', [[
            $this->h('No', w: '6%'), $this->h('Berita Acara'), $this->h('No. Dokumen', w: '26%'), $this->h('Status', w: '14%'), $this->h('Tgl Disetujui', w: '16%'),
        ]], $rows, $records->isNotEmpty(), 'Dokumen lengkap tiap Berita Acara dicetak dari menu Pengusahaan Operasi → Berita Acara.', source: 'Berita Acara');
    }

    /**
     * @param  list<list<array<string, mixed>>>  $head
     * @param  list<list<array<string, mixed>>>  $rows
     * @return array<string, mixed>
     */
    private function section(string $key, string $title, string $orientation, array $head, array $rows, bool $filled, ?string $note = null, string $source = '-'): array
    {
        return [
            'key' => $key,
            'no' => 0,
            'title' => $title,
            'number' => $this->number,
            'orientation' => $orientation,
            'meta' => [],
            'head' => $head,
            'rows' => $rows,
            'filled' => $filled,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'source' => $source,
        ];
    }

    /** @return array<string, mixed> */
    private function h(string $text, int $c = 1, int $r = 1, ?string $w = null): array
    {
        return array_filter(['t' => $text, 'c' => $c, 'r' => $r, 'w' => $w], fn ($v): bool => $v !== null);
    }

    /** @return array<string, mixed> */
    private function cell(string $text, string $align = 'l', bool $bold = false, int $r = 1): array
    {
        return ['t' => $text, 'a' => $align, 'b' => $bold, 'r' => $r];
    }

    /** @return list<array<string, mixed>> */
    private function sectionRow(string $text, int $span): array
    {
        return [['t' => $text, 'c' => $span, 'b' => true, 's' => true]];
    }

    private function num(float|int|string|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    private function toFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
