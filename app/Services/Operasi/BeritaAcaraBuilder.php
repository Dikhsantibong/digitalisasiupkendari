<?php

namespace App\Services\Operasi;

use App\Enums\BeritaAcaraType;
use App\Enums\CalibrationFactorType;
use App\Enums\StockItemType;
use App\Models\BbmType;
use App\Models\CalibrationFactor;
use App\Models\DailyEngineReport;
use App\Models\DailyFeederReading;
use App\Models\Feeder;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\LubricantReceipt;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\PhysicalStockTake;
use App\Models\Unit;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;

/**
 * Assembles the payload for a Berita Acara. Every number is derived here from
 * the same stored data the grids and reports use; only the physical opname and
 * the selisih note are manual (they come from physical_stock_takes and the
 * user). The letter number comes from {@see DocumentTemplateService}.
 *
 * The lubricant "pemakaian sendiri" is left at zero: usage is not tracked per
 * lubricant type in the source data, so it is not guessed.
 */
class BeritaAcaraBuilder
{
    public function __construct(
        private readonly OperasiCalculator $calculator,
        private readonly DocumentTemplateService $templates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, BeritaAcaraType $type, int $month, int $year): array
    {
        return match ($type) {
            BeritaAcaraType::Hsd, BeritaAcaraType::Mfo => $this->buildFuel($unit, $type, $month, $year),
            BeritaAcaraType::Pelumas => $this->buildLubricant($unit, $month, $year),
            BeritaAcaraType::Feeder => $this->buildFeeder($unit, $month, $year),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFuel(Unit $unit, BeritaAcaraType $type, int $month, int $year): array
    {
        $tankFuel = $type->tankFuelType();
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $persediaanAwal = $this->previousPhysicalFuel($unit, $type, $month, $year);

        $penerimaan = FuelReceipt::query()
            ->where('unit_id', $unit->id)
            ->where('fuel_type', $tankFuel->value)
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->sum('volume_liter');
        $penerimaan = (float) $penerimaan;

        $a = $persediaanAwal + $penerimaan;

        $pemakaianItems = [];
        $machines = Machine::query()->where('unit_id', $unit->id)->orderBy('name')->get();
        foreach ($machines as $machine) {
            if ($type === BeritaAcaraType::Mfo && ! ($machine->fuel_type?->usesMfo() ?? false)) {
                continue;
            }
            $summary = $this->calculator->buildDailyEngineGrid($machine, $month, $year)['summary']['total'] ?? [];
            $liter = (float) ($type === BeritaAcaraType::Hsd
                ? ($summary['pemakaian_hsd'] ?? 0)
                : ($summary['pemakaian_mfo'] ?? 0));

            if ($liter > 0) {
                $pemakaianItems[] = ['mesin' => $machine->name, 'liter' => round($liter, 2)];
            }
        }
        $b = array_sum(array_column($pemakaianItems, 'liter'));

        $c = 0.0; // Pengiriman: not modelled; defaults to zero.
        $d = $a - $b - $c;

        $fisikItems = $this->currentPhysicalFuel($unit, $type, $month, $year);
        $e = array_sum(array_column($fisikItems, 'liter'));
        $f = $e - $d;

        return [
            ...$this->commonPayload($unit, $type, $month, $year),
            'is_fuel' => true,
            'fuel_label' => $tankFuel->label(),
            'persediaan_awal' => round($persediaanAwal, 2),
            'penerimaan_total' => round($penerimaan, 2),
            'penerimaan_range' => Indonesian::longDate($start).' s/d '.Indonesian::longDate($end),
            'jumlah_stock' => round($a, 2),
            'pemakaian' => $pemakaianItems,
            'pemakaian_total' => round($b, 2),
            'pengiriman' => round($c, 2),
            'administrasi' => round($d, 2),
            'fisik' => $fisikItems,
            'fisik_total' => round($e, 2),
            'selisih' => round($f, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLubricant(Unit $unit, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $lubricants = LubricantType::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        [$prevMonth, $prevYear] = $this->previousPeriod($month, $year);

        $rows = [];
        foreach ($lubricants as $lubricant) {
            $awal = (float) PhysicalStockTake::query()
                ->where('unit_id', $unit->id)
                ->where('item_type', StockItemType::Lubricant->value)
                ->where('lubricant_type_id', $lubricant->id)
                ->whereHas('reportPeriod', fn ($q) => $q->where('month', $prevMonth)->where('year', $prevYear))
                ->sum('physical_qty_liter');

            $penerimaan = (float) LubricantReceipt::query()
                ->where('unit_id', $unit->id)
                ->where('lubricant_type_id', $lubricant->id)
                ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
                ->sum('volume');

            $stock = $awal + $penerimaan;
            $pemakaian = 0.0; // Not tracked per lubricant; filled manually if needed.
            $pengiriman = 0.0;
            $administrasi = $stock - $pemakaian - $pengiriman;

            $fisik = PhysicalStockTake::query()
                ->where('unit_id', $unit->id)
                ->where('item_type', StockItemType::Lubricant->value)
                ->where('lubricant_type_id', $lubricant->id)
                ->whereHas('reportPeriod', fn ($q) => $q->where('month', $month)->where('year', $year))
                ->first();

            $fisikLiter = (float) ($fisik?->physical_qty_liter ?? 0);

            $rows[] = [
                'jenis' => $lubricant->name,
                'satuan' => $lubricant->unit_of_measure->label(),
                'awal' => round($awal, 2),
                'penerimaan' => round($penerimaan, 2),
                'stock' => round($stock, 2),
                'pemakaian' => round($pemakaian, 2),
                'pengiriman' => round($pengiriman, 2),
                'administrasi' => round($administrasi, 2),
                'fisik_liter' => round($fisikLiter, 2),
                'fisik_drum' => $fisik?->physical_drum,
                'fisik_cm' => $fisik?->physical_cm,
                'selisih' => round($fisikLiter - $administrasi, 2),
            ];
        }

        return [
            ...$this->commonPayload($unit, BeritaAcaraType::Pelumas, $month, $year),
            'is_fuel' => false,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFeeder(Unit $unit, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $feeders = Feeder::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $feederRows = [];
        $totalExport = 0.0;
        $totalImport = 0.0;

        foreach ($feeders as $feeder) {
            $prevReading = DailyFeederReading::query()
                ->where('feeder_id', $feeder->id)
                ->where('report_date', '<', $start->toDateString())
                ->orderByDesc('report_date')
                ->value('stand_akhir');

            $lastReading = DailyFeederReading::query()
                ->where('feeder_id', $feeder->id)
                ->where('report_date', '>=', $start->toDateString())
                ->where('report_date', '<=', $end->toDateString().' 23:59:59')
                ->orderByDesc('report_date')
                ->value('stand_akhir');

            $awal = (float) ($prevReading ?? 0);
            $akhir = (float) ($lastReading ?? $awal);
            $fKali = 1.0;
            $diff = $akhir - $awal;
            $exportHasil = round(($diff > 0 ? $diff : 0) * $fKali, 2);

            $feederRows[] = [
                'feeder_id' => $feeder->id,
                'feeder_name' => $feeder->name,
                'export' => [
                    'awal' => $awal,
                    'akhir' => $akhir,
                    'f_kali' => $fKali,
                    'hasil' => $exportHasil,
                ],
                'import' => [
                    'awal' => 0.0,
                    'akhir' => 0.0,
                    'f_kali' => $fKali,
                    'hasil' => 0.0,
                ],
                'keterangan' => '',
            ];

            $totalExport += $exportHasil;
        }

        return [
            ...$this->commonPayload($unit, BeritaAcaraType::Feeder, $month, $year),
            'is_fuel' => false,
            'is_feeder' => true,
            'feeder_rows' => $feederRows,
            'totals' => [
                'jumlah_export' => round($totalExport, 2),
                'jumlah_import' => round($totalImport, 2),
                'total_unit' => round($totalExport - $totalImport, 2),
            ],
            'catatan' => '',
            'attachments' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFlowmeter(Unit $unit, int $month, int $year, ?string $fuelCode = null): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = (int) $start->daysInMonth;

        // 1. Resolve Fuel Name from master data / active unit fuel
        $availableFuels = BbmType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $tankFuel = FuelTank::query()->where('unit_id', $unit->id)->where('is_active', true)->value('fuel_type');

        $selectedFuel = null;
        if ($fuelCode) {
            $selectedFuel = $availableFuels->firstWhere('code', $fuelCode)
                ?? $availableFuels->firstWhere('code', strtoupper($fuelCode));
        }
        if ($selectedFuel === null && $tankFuel) {
            $selectedFuel = $availableFuels->first(function (BbmType $b) use ($tankFuel) {
                return strtolower($b->code) === strtolower($tankFuel->value)
                    || strtolower($b->category ?? '') === strtolower($tankFuel->value);
            });
        }
        if ($selectedFuel === null) {
            $selectedFuel = $availableFuels->firstWhere('code', 'HSD') ?? $availableFuels->first();
        }

        $fuelName = $selectedFuel?->code ?? 'HSD';
        $fuelLabel = $selectedFuel?->name ?? 'High Speed Diesel';

        // 2. Active machines for the unit
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flowmeterMachines = [];
        $runningAkhir = [];

        foreach ($machines as $machine) {
            // Stand awal bulan lalu (last reading before this month)
            $prevStand = DailyEngineReport::query()
                ->where('engine_id', $machine->id)
                ->where('report_date', '<', $start->toDateString())
                ->whereNotNull('flowmeter_hsd_stand_akhir')
                ->orderByDesc('report_date')
                ->value('flowmeter_hsd_stand_akhir');
            $standAwalBlnLalu = (float) ($prevStand ?? 0.0);

            // Faktor koreksi from CalibrationFactor or default
            $cf = CalibrationFactor::query()
                ->where('unit_id', $unit->id)
                ->where(function ($q) use ($machine) {
                    $q->where('engine_id', $machine->id)->orWhereNull('engine_id');
                })
                ->where('factor_type', CalibrationFactorType::Hsd->value)
                ->where('effective_date', '<=', $end->toDateString())
                ->orderByRaw('engine_id IS NULL')
                ->orderByDesc('effective_date')
                ->value('value');
            $faktorKoreksi = $cf !== null ? (float) $cf : 1.0;
            $faktorKali = 1.0;

            $flowmeterMachines[] = [
                'id' => $machine->id,
                'name' => $machine->name,
                'stand_awal_bln_lalu' => $standAwalBlnLalu,
                'faktor_koreksi' => $faktorKoreksi,
                'faktor_kali' => $faktorKali,
            ];

            $runningAkhir[$machine->id] = $standAwalBlnLalu;
        }

        // 3. Preload daily reports for this month
        $reports = DailyEngineReport::query()
            ->where('unit_id', $unit->id)
            ->where('report_date', '>=', $start->toDateString())
            ->where('report_date', '<=', $end->toDateString().' 23:59:59')
            ->get()
            ->groupBy(fn ($r) => $r->engine_id.'|'.(int) $r->report_date->day);

        $flowmeterRows = [];
        $machineTotals = [];
        foreach ($machines as $m) {
            $machineTotals[$m->id] = [
                'awal' => $runningAkhir[$m->id],
                'akhir' => 0.0,
                'pemakaian' => 0.0,
            ];
        }
        $totalAdm = 0.0;
        $totalReal = 0.0;

        foreach (range(1, $daysInMonth) as $day) {
            $rowMachines = [];
            $dailyPemakaianSum = 0.0;

            foreach ($flowmeterMachines as $fmMach) {
                $mId = $fmMach['id'];
                $report = $reports->get($mId.'|'.$day)?->first();
                $akhirRaw = $report?->flowmeter_hsd_stand_akhir;
                $akhir = $akhirRaw !== null ? (float) $akhirRaw : 0.0;

                // Formula 1: =IF(F17=0; 0; F16)
                if ($day === 1) {
                    $awal = $fmMach['stand_awal_bln_lalu'];
                } else {
                    $awal = ($akhir == 0.0) ? 0.0 : $runningAkhir[$mId];
                }

                if ($akhir > 0) {
                    $runningAkhir[$mId] = $akhir;
                    $machineTotals[$mId]['akhir'] = $akhir;
                }

                // Pemakaian = (akhir - awal) * faktor_kali * faktor_koreksi
                $pemakaian = 0.0;
                if ($akhir > 0 && $awal > 0 && $akhir >= $awal) {
                    $diff = $akhir - $awal;
                    $pemakaian = round($diff * $fmMach['faktor_kali'] * $fmMach['faktor_koreksi'], 2);
                }

                $rowMachines[$mId] = [
                    'machine_id' => $mId,
                    'awal' => round($awal, 2),
                    'akhir' => round($akhir, 2),
                    'pemakaian' => round($pemakaian, 2),
                ];

                $dailyPemakaianSum += $pemakaian;
                $machineTotals[$mId]['pemakaian'] += $pemakaian;
            }

            // Formula 2: ADM = =D13+G13+J13+M13+P13 (sum of all machines pemakaian)
            $adm = round($dailyPemakaianSum, 2);
            $real = 0.0;
            // Formula 3: SELISIH = =AP13-AO13 (Real - ADM)
            $selisih = round($real - $adm, 2);

            $flowmeterRows[] = [
                'tgl' => $day,
                'machines' => $rowMachines,
                'adm' => $adm,
                'real' => $real,
                'selisih' => $selisih,
            ];

            $totalAdm += $adm;
            $totalReal += $real;
        }

        // Format machine totals
        $formattedMachineTotals = [];
        foreach ($machines as $m) {
            $formattedMachineTotals[$m->id] = [
                'awal' => round($machineTotals[$m->id]['awal'], 2),
                'akhir' => round($machineTotals[$m->id]['akhir'], 2),
                'pemakaian' => round($machineTotals[$m->id]['pemakaian'], 2),
            ];
        }

        $flowmeterTotals = [
            'machines' => $formattedMachineTotals,
            'adm' => round($totalAdm, 2),
            'real' => round($totalReal, 2),
            'selisih' => round($totalReal - $totalAdm, 2),
        ];

        $common = $this->commonPayload($unit, BeritaAcaraType::Flowmeter, $month, $year);
        $common['document']['title'] = 'STAND FLOW METER '.strtoupper($fuelName);

        return [
            ...$common,
            'is_fuel' => false,
            'is_feeder' => false,
            'is_flowmeter' => true,
            'fuel_name' => $fuelName,
            'fuel_label' => $fuelLabel,
            'available_fuels' => $availableFuels->map(fn (BbmType $b) => ['code' => $b->code, 'name' => $b->name])->all(),
            'flowmeter_machines' => $flowmeterMachines,
            'flowmeter_rows' => $flowmeterRows,
            'flowmeter_totals' => $flowmeterTotals,
            'catatan' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commonPayload(Unit $unit, BeritaAcaraType $type, int $month, int $year): array
    {
        $unit->loadMissing('serviceUnit:id,name');
        $template = $this->templates->resolve($type, $unit);
        $today = Carbon::now();

        return [
            'type' => $type->value,
            'document' => [
                'number' => $template->document_number,
                'title' => $template->title,
                'revision' => $template->revision,
                'revision_date' => $template->revision_date?->toDateString(),
            ],
            'unit' => [
                'name' => $unit->name,
                'service_unit' => $unit->serviceUnit?->name,
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => Indonesian::monthName($month).' '.$year,
            ],
            'narrative' => [
                'hari' => Indonesian::dayName($today),
                'tanggal_terbilang' => Indonesian::terbilang((int) $today->day),
                'bulan' => Indonesian::monthName((int) $today->month),
                'tahun_terbilang' => Indonesian::terbilang((int) $today->year),
                'tanggal_penuh' => $today->format('d-m-Y'),
            ],
            'print_place_date' => 'Kendari, '.Indonesian::longDate($today),
            'signers' => [
                'manajer' => $this->employeeName($unit, 'manajer'),
                'tl_operasi' => $this->employeeName($unit, 'operasi'),
            ],
        ];
    }

    private function previousPhysicalFuel(Unit $unit, BeritaAcaraType $type, int $month, int $year): float
    {
        [$prevMonth, $prevYear] = $this->previousPeriod($month, $year);

        return (float) PhysicalStockTake::query()
            ->where('unit_id', $unit->id)
            ->where('item_type', StockItemType::Fuel->value)
            ->whereIn('tank_id', $this->tankIds($unit, $type))
            ->whereHas('reportPeriod', fn ($q) => $q->where('month', $prevMonth)->where('year', $prevYear))
            ->sum('physical_qty_liter');
    }

    /**
     * @return list<array{tangki: string, liter: float}>
     */
    private function currentPhysicalFuel(Unit $unit, BeritaAcaraType $type, int $month, int $year): array
    {
        return PhysicalStockTake::query()
            ->where('unit_id', $unit->id)
            ->where('item_type', StockItemType::Fuel->value)
            ->whereIn('tank_id', $this->tankIds($unit, $type))
            ->whereHas('reportPeriod', fn ($q) => $q->where('month', $month)->where('year', $year))
            ->with('tank:id,name')
            ->get()
            ->map(fn (PhysicalStockTake $stock): array => [
                'tangki' => $stock->tank?->name ?? '—',
                'liter' => round((float) $stock->physical_qty_liter, 2),
            ])
            ->all();
    }

    /**
     * @return list<int>
     */
    private function tankIds(Unit $unit, BeritaAcaraType $type): array
    {
        return FuelTank::query()
            ->where('unit_id', $unit->id)
            ->where('fuel_type', $type->tankFuelType()->value)
            ->pluck('id')
            ->all();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function previousPeriod(int $month, int $year): array
    {
        return $month === 1 ? [12, $year - 1] : [$month - 1, $year];
    }

    private function employeeName(Unit $unit, string $positionKeyword): ?string
    {
        if (str_contains(strtolower($positionKeyword), 'manajer') || str_contains(strtolower($positionKeyword), 'manager')) {
            return $unit->manager()?->name;
        }

        return $unit->employees()
            ->where('is_active', true)
            ->where('position', 'like', "%{$positionKeyword}%")
            ->orderBy('id')
            ->value('name');
    }
}
