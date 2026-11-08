<?php

namespace App\Services\Dashboard;

use App\Enums\PersediaanJenis;
use App\Models\FuelTank;
use App\Models\LogistikFormRow;
use App\Models\OperasiPemakaianBbm;
use App\Models\OperasiPersediaan;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The Logistik section of the dashboard, read from the visible units:
 * Laporan Kondisi Stok Tools dan Material (the latest month each unit filled;
 * stok akhir = stok awal + masuk − keluar, ROP = pemakaian rata-rata × ILT +
 * safety stock, kritis = stok akhir ≤ ROP on a row with any movement),
 * Persediaan Bahan Bakar / Pelumas (penerimaan, saldo akhir), Pemakaian Bahan
 * Bakar and the capacity of the active fuel tanks (Data Master).
 */
class LogistikDashboard
{
    private const STOCK_FORM = 'kondisi-stok';

    /**
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    public function build(Collection $units, Carbon $now): array
    {
        $unitIds = $units->pluck('id')->all();
        $months = DashboardSupport::months($now);
        $last = count($months) - 1;
        $first = $months[0];
        $since = fn ($query) => $query->where(fn ($q) => $q->where('year', '>', $first['year'])->orWhere(fn ($q2) => $q2->where('year', $first['year'])->where('month', '>=', $first['month'])));

        $persediaan = $since(OperasiPersediaan::query()->whereIn('unit_id', $unitIds))->get(['unit_id', 'jenis', 'month', 'year', 'entries']);
        $penerimaan = fn (PersediaanJenis $jenis): array => array_map(fn (array $m): float => round((float) $persediaan
            ->filter(fn (OperasiPersediaan $r): bool => $r->jenis === $jenis && $r->month === $m['month'] && $r->year === $m['year'])
            ->sum(fn (OperasiPersediaan $r): float => $this->received($r->entries ?? [])), 2), $months);
        $bbmIn = array_map(fn (float $liter): float => round($liter / 1000, 2), $penerimaan(PersediaanJenis::Bbm));
        $pelumasIn = $penerimaan(PersediaanJenis::Pelumas);
        $usage = $since(OperasiPemakaianBbm::query()->whereIn('unit_id', $unitIds))->get(['month', 'year', 'grand_total_liter']);
        $bbmOut = array_map(fn (array $m): float => round((float) $usage->where('month', $m['month'])->where('year', $m['year'])->sum('grand_total_liter') / 1000, 2), $months);

        $stock = $this->stockRows($units, $now);
        $critical = array_values(array_filter($stock, fn (array $row): bool => $row['critical']));
        usort($critical, fn (array $a, array $b): int => ($a['stock'] - $a['min']) <=> ($b['stock'] - $b['min']));

        return [
            'kpis' => [
                DashboardSupport::kpi('nilai', 'Nilai Persediaan', round(array_sum(array_column($stock, 'value')), 2), null, 'rupiah', 'up'),
                DashboardSupport::kpi('item', 'Item Material', count($stock), null, 'number', 'up'),
                DashboardSupport::kpi('kritis', 'Stok Kritis', count($critical), 'item', 'number', 'down'),
                DashboardSupport::kpi('bbm', 'Penerimaan BBM', $bbmIn[$last], 'kL', 'decimal', 'up', $bbmIn[$last - 1], $bbmIn),
                DashboardSupport::kpi('pelumas', 'Penerimaan Pelumas', $pelumasIn[$last], 'L', 'number', 'up', $pelumasIn[$last - 1], $pelumasIn),
            ],
            'tanks' => $this->tanks($units),
            'fuel_flow' => [
                'labels' => array_column($months, 'label'),
                'series' => [
                    ['name' => 'Penerimaan (kL)', 'values' => $bbmIn],
                    ['name' => 'Pemakaian (kL)', 'values' => $bbmOut],
                ],
            ],
            'category' => [
                ['label' => 'Aman', 'value' => count(array_filter($stock, fn (array $row): bool => ! $row['critical']))],
                ['label' => 'Di Bawah ROP', 'value' => count(array_filter($critical, fn (array $row): bool => $row['stock'] > 0))],
                ['label' => 'Habis', 'value' => count(array_filter($critical, fn (array $row): bool => $row['stock'] <= 0))],
            ],
            'critical' => array_map(fn (array $row): array => array_intersect_key($row, array_flip(['code', 'name', 'unit', 'stock', 'min', 'uom'])), array_slice($critical, 0, 10)),
        ];
    }

    /**
     * Penerimaan of a Persediaan sheet (every item, every day).
     *
     * @param  array<array-key, mixed>  $entries
     */
    private function received(array $entries): float
    {
        $total = 0.0;
        foreach ($entries as $days) {
            foreach ((array) $days as $fields) {
                $total += is_array($fields) && is_numeric($fields['penerimaan'] ?? null) ? (float) $fields['penerimaan'] : 0.0;
            }
        }

        return $total;
    }

    /**
     * The material rows of each unit's latest Kondisi Stok (up to this month).
     *
     * @param  Collection<int, Unit>  $units
     * @return list<array{code: string, name: string, unit: string, stock: float, min: float, uom: string, value: float, critical: bool}>
     */
    private function stockRows(Collection $units, Carbon $now): array
    {
        $rows = [];

        foreach ($units as $unit) {
            $latest = LogistikFormRow::query()
                ->where('unit_id', $unit->id)->where('form', self::STOCK_FORM)
                ->where(fn ($q) => $q->where('year', '<', $now->year)->orWhere(fn ($q2) => $q2->where('year', $now->year)->where('month', '<=', $now->month)))
                ->orderByDesc('year')->orderByDesc('month')
                ->first(['year', 'month']);
            if ($latest === null) {
                continue;
            }

            $saved = LogistikFormRow::query()->where('unit_id', $unit->id)->where('form', self::STOCK_FORM)
                ->where('year', $latest->year)->where('month', $latest->month)->orderBy('sort_order')->get(['data']);

            foreach ($saved as $row) {
                $data = $row->data ?? [];
                $name = trim((string) ($data['nama'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $number = fn (string $key): float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : 0.0;
                $stock = $number('stok_awal') + $number('material_masuk') - $number('material_keluar');
                $rop = $number('pemakaian_rata') * $number('ilt') + $number('safety_stock');
                $moving = $number('stok_awal') > 0 || $number('material_masuk') > 0 || $number('material_keluar') > 0 || $number('pemakaian_rata') > 0;

                $rows[] = [
                    'code' => trim((string) ($data['kode_material'] ?? '')) ?: trim((string) ($data['stock_code'] ?? '')) ?: '—',
                    'name' => $name,
                    'unit' => $unit->name,
                    'stock' => round($stock, 2),
                    'min' => round($rop, 2),
                    'uom' => trim((string) ($data['satuan'] ?? '')),
                    'value' => round(max(0, $stock) * $number('harga_satuan'), 2),
                    'critical' => $moving && $stock <= $rop,
                ];
            }
        }

        return $rows;
    }

    /**
     * Fill level of each unit's active fuel tanks: the saldo akhir of its
     * latest Persediaan Bahan Bakar ÷ the tank capacity (Data Master).
     *
     * @param  Collection<int, Unit>  $units
     * @return list<array{label: string, value: int, capacity: float}>
     */
    private function tanks(Collection $units): array
    {
        $capacity = FuelTank::query()->whereIn('unit_id', $units->pluck('id'))->where('is_active', true)
            ->groupBy('unit_id')->selectRaw('unit_id, sum(capacity_liter) as liter')->pluck('liter', 'unit_id');
        $rows = [];

        foreach ($units as $unit) {
            $liter = (float) ($capacity[$unit->id] ?? 0);
            if ($liter <= 0) {
                continue;
            }

            $closing = OperasiPersediaan::query()->where('unit_id', $unit->id)->where('jenis', PersediaanJenis::Bbm)
                ->orderByDesc('year')->orderByDesc('month')->value('closing');
            $stock = array_sum(array_map('floatval', (array) ($closing ?? [])));

            $rows[] = [
                'label' => $unit->name,
                'value' => (int) max(0, min(100, round($stock / $liter * 100))),
                'capacity' => round($liter / 1000, 1),
            ];
        }

        return $rows;
    }
}
