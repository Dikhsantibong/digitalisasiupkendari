<?php

namespace App\Services\Operasi;

use App\Enums\BeritaAcaraType;
use App\Enums\StockItemType;
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
        return $type->isFuel()
            ? $this->buildFuel($unit, $type, $month, $year)
            : $this->buildLubricant($unit, $month, $year);
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
        return $unit->employees()
            ->where('is_active', true)
            ->where('position', 'like', "%{$positionKeyword}%")
            ->orderBy('id')
            ->value('name');
    }
}
