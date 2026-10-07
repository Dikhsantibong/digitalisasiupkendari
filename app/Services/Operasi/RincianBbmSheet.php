<?php

namespace App\Services\Operasi;

use App\Enums\PersediaanJenis;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\OperasiPemakaianBbm;
use App\Models\OperasiRekap;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Perincian Bahan Bakar and Rekap Bahan Bakar (Pemakaian Bahan Bakar per
 * jenis): one month of every jenis BBM of the unit ({@see UnitFuelTypes}).
 *
 * Read from the other sheets: persediaan awal, penerimaan per periode (I–IV)
 * and the pengiriman TUG 8 / pinjam of Persediaan Bahan Bakar; pemakaian
 * mesin of Pemakaian Bahan Bakar; the storage tanks of the master.
 *
 * Typed in, stored once as an {@see OperasiRekap} (`rincian-bbm`) per jenis
 * BBM so both sheets always agree: pengembalian (TUG 10), koreksi /
 * peminjaman, the physical stock (Perincian) and the non-mesin pemakaian per
 * mesin — on operasi / test mesin, cuci HAR, bocor / dll (Rekap).
 *
 * Sisa sesuai perhitungan = awal + pengembalian + penerimaan − pemakaian
 * (mesin + non-mesin) − pengiriman − koreksi; selisih = fisik − perhitungan.
 */
class RincianBbmSheet
{
    public const JENIS = 'rincian-bbm';

    /** @var array<string, string> non-mesin pemakaian => label (Rekap Bahan Bakar columns) */
    public const LAIN = ['test' => 'On Operasi / Test Mesin', 'cuci' => 'Cuci HAR', 'bocor' => 'Bocor / DLL'];

    public function __construct(private readonly PersediaanSheet $persediaan) {}

    /**
     * @return array{
     *     fuels: list<array{key: string, name: string, code: string|null}>,
     *     machines: list<array{id: int, name: string, merk: string|null, type: string|null, serial_number: string|null}>,
     *     tanks: array<string, list<array{name: string, capacity_liter: float|null}>>,
     *     auto: array{
     *         awal: array<string, float>,
     *         penerimaan: array<string, list<array{label: string, from: int, to: int, amount: float}>>,
     *         pengiriman: array<string, float>,
     *         pemakaian: array<int, array<string, float>>
     *     },
     *     manual: array<string, array{pengembalian: array{tanggal: string|null, amount: float}, koreksi: array{keterangan: string|null, amount: float}, fisik: float|null, lain: array<int, array<string, float>>}>,
     *     record: OperasiRekap|null,
     *     manager: string|null
     * }
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $jenis = PersediaanJenis::Bbm;
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $fuels = $this->persediaan->items($unit, $jenis);
        $codes = array_column($fuels, 'key');
        $persediaanRecord = $this->persediaan->record($unit, $jenis, $month, $year);
        $opening = $this->persediaan->openingOf($persediaanRecord, $this->persediaan->carriedOpening($unit, $jenis, $month, $year, $fuels), $fuels);
        $sheet = $this->persediaan->compute($jenis, $fuels, $opening, $persediaanRecord?->entries ?? [], $this->persediaan->pemakaian($unit, $jenis, $month, $year, $fuels), $daysInMonth);
        $periods = $this->persediaan->periods($jenis, $daysInMonth);
        $kirimFields = array_keys($this->persediaan->kirimColumns($jenis));

        $penerimaan = [];
        $pengiriman = [];
        foreach ($codes as $code) {
            $penerimaan[$code] = array_map(fn (array $period, int $index): array => [
                'label' => $period['label'],
                'from' => $period['from'],
                'to' => $period['to'],
                'amount' => (float) $sheet[$code]['periods'][$index]['penerimaan'],
            ], $periods, array_keys($periods));
            $pengiriman[$code] = round(array_sum(array_map(fn (string $field): float => (float) $sheet[$code]['total'][$field], $kirimFields)), 2);
        }

        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'merk', 'type', 'serial_number']);
        $readings = OperasiPemakaianBbm::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first()?->raw_readings ?? [];
        $pemakaian = [];
        foreach ($machines as $machine) {
            foreach ($codes as $code) {
                $pemakaian[$machine->id][$code] = round(array_sum(array_map('floatval', $readings["{$code}_{$machine->id}"] ?? [])), 2);
            }
        }

        $tanks = array_fill_keys($codes, []);
        FuelTank::query()->with('bbmType')->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->each(function (FuelTank $tank) use (&$tanks): void {
                $code = $tank->bbmType?->code ?? strtoupper((string) $tank->fuel_type?->value);
                if (array_key_exists($code, $tanks)) {
                    $tanks[$code][] = ['name' => $tank->name, 'capacity_liter' => $tank->capacity_liter === null ? null : (float) $tank->capacity_liter];
                }
            });

        $record = OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', self::JENIS)->where('month', $month)->where('year', $year)->first();

        return [
            'fuels' => array_map(fn (array $fuel): array => ['key' => $fuel['key'], 'name' => $fuel['name'], 'code' => $fuel['code']], $fuels),
            'machines' => $machines->map(fn (Machine $machine): array => $machine->only(['id', 'name', 'merk', 'type', 'serial_number']))->all(),
            'tanks' => $tanks,
            'auto' => ['awal' => $opening, 'penerimaan' => $penerimaan, 'pengiriman' => $pengiriman, 'pemakaian' => $pemakaian],
            'manual' => $this->sanitize($record?->overrides ?? [], $codes, $machines->pluck('id')->all()),
            'record' => $record,
            'manager' => $unit->manager()?->name,
        ];
    }

    /**
     * Keep only the typed-in parts, per jenis BBM of the unit.
     *
     * @param  array<array-key, mixed>  $input  jenis BBM code => typed-in parts
     * @param  list<string>  $codes
     * @param  list<int>  $machineIds
     * @return array<string, array{pengembalian: array{tanggal: string|null, amount: float}, koreksi: array{keterangan: string|null, amount: float}, fisik: float|null, lain: array<int, array<string, float>>}>
     */
    public function sanitize(array $input, array $codes, array $machineIds): array
    {
        $amount = fn (mixed $value): float => is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : 0.0;
        $clean = [];

        foreach ($codes as $code) {
            $fuel = is_array($input[$code] ?? null) ? $input[$code] : [];
            $lain = [];
            foreach ($machineIds as $machineId) {
                foreach (array_keys(self::LAIN) as $field) {
                    $value = $amount($fuel['lain'][$machineId][$field] ?? null);
                    if ($value > 0) {
                        $lain[$machineId][$field] = $value;
                    }
                }
            }

            $clean[$code] = [
                'pengembalian' => [
                    'tanggal' => filled($fuel['pengembalian']['tanggal'] ?? null) ? (string) $fuel['pengembalian']['tanggal'] : null,
                    'amount' => $amount($fuel['pengembalian']['amount'] ?? null),
                ],
                'koreksi' => [
                    'keterangan' => filled($fuel['koreksi']['keterangan'] ?? null) ? mb_substr(trim((string) $fuel['koreksi']['keterangan']), 0, 150) : null,
                    'amount' => $amount($fuel['koreksi']['amount'] ?? null),
                ],
                // A physical stock of 0 is a real count, so it is kept.
                'fisik' => is_numeric($fuel['fisik'] ?? null) && (float) $fuel['fisik'] >= 0 ? round((float) $fuel['fisik'], 2) : null,
                'lain' => $lain,
            ];
        }

        return $clean;
    }

    /**
     * Per jenis BBM: the figures of both sheets.
     *
     * @param  array{fuels: list<array{key: string}>, auto: array<string, mixed>, manual: array<string, mixed>}  $data
     * @return array<string, array<string, float>>
     */
    public function totals(array $data): array
    {
        $auto = $data['auto'];
        $rows = [];

        foreach (array_column($data['fuels'], 'key') as $code) {
            $manual = $data['manual'][$code];
            $penerimaan = array_sum(array_column($auto['penerimaan'][$code], 'amount'));
            $totalPersediaan = (float) $auto['awal'][$code] + $manual['pengembalian']['amount'] + $penerimaan;
            $mesin = array_sum(array_map(fn (array $machine): float => (float) ($machine[$code] ?? 0), $auto['pemakaian']));
            $lain = [];
            foreach (array_keys(self::LAIN) as $field) {
                $lain[$field] = array_sum(array_map(fn (array $machine): float => (float) ($machine[$field] ?? 0), $manual['lain']));
            }
            $jumlahPemakaian = $mesin + array_sum($lain);
            $totalPengeluaran = $jumlahPemakaian + (float) $auto['pengiriman'][$code] + $manual['koreksi']['amount'];
            $sisa = $totalPersediaan - $totalPengeluaran;
            $fisik = $manual['fisik'] ?? $sisa;

            $rows[$code] = array_map(fn (float $value): float => round($value, 2), [
                'awal' => (float) $auto['awal'][$code],
                'pengembalian' => $manual['pengembalian']['amount'],
                'penerimaan' => $penerimaan,
                'total_persediaan' => $totalPersediaan,
                'mesin' => $mesin,
                ...$lain,
                'jumlah_pemakaian' => $jumlahPemakaian,
                'pengiriman' => (float) $auto['pengiriman'][$code],
                'koreksi' => $manual['koreksi']['amount'],
                'total_pengeluaran' => $totalPengeluaran,
                'sisa' => $sisa,
                'fisik' => $fisik,
                'selisih' => $fisik - $sisa,
            ]);
        }

        return $rows;
    }
}
