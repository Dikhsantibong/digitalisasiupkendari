<?php

namespace App\Services\Operasi;

use App\Enums\MesinHarianJenis;
use App\Models\OperasiPemakaianBbm;
use App\Models\OperasiRekap;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Data Kinerja Pembangkit Termal — percentages per machine, computed from the
 * other sheets (Kinerja Unit Mesin for daya terpasang, kondisi and the hours;
 * Stand kWh Harian for the kWh produksi; Pemakaian Bahan Bakar for the liters;
 * Jumlah Kali Gangguan for SdOF), each correctable:
 *
 * - CF  = kWh / (daya terpasang × jam periode) × 100
 * - OF  = kWh / (daya terpasang × jam operasi) × 100
 * - SF  = jam operasi / jam periode × 100
 * - POF = SOF = jam pemeliharaan / jam periode × 100
 * - FOF = jam gangguan / jam periode × 100
 * - EAF = 100 − FOF − POF
 * - FOR = EFOR = jam gangguan / (jam gangguan + jam operasi) × 100
 * - SdOF = jumlah kali gangguan
 * - EFF = kWh × 860 / (liter BBM × 10.289 × 0,949) × 100
 *
 * Jam periode = days of the month × 24. Machines "Tidak Operasi" show
 * "ATTB HAR" instead of figures and are left out of the averages.
 */
class KinerjaTermalSheet
{
    public const JENIS = 'kinerja-termal';

    /** @var list<string> */
    public const FIELDS = ['cf', 'of', 'sf', 'eaf', 'pof', 'fof', 'for', 'sof', 'efor', 'sdof', 'eff'];

    public const KCAL_PER_KWH = 860;

    public const KCAL_PER_LITER = 10289;

    public const DENSITY = 0.949;

    public function __construct(
        private readonly KinerjaMesinSheet $kinerja,
        private readonly KwhSheet $kwh,
        private readonly PemakaianBbmSheet $bbm,
        private readonly MesinHarianSheet $mesin,
    ) {}

    public function record(Unit $unit, int $month, int $year): ?OperasiRekap
    {
        return OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', self::JENIS)->where('month', $month)->where('year', $year)->first();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, averages: array<string, float>, jam_periode: float, constants: array<string, float>}
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $jamPeriode = (float) Carbon::create($year, $month, 1)->daysInMonth * 24;
        $kinerja = $this->kinerja->build($unit, $month, $year)['rows'];
        $produksi = collect($this->kwh->harian($unit, $month, $year))->mapWithKeys(fn (array $sheet): array => [$sheet['machine']['id'] => $sheet['totals']['produksi']]);
        $liters = $this->litersByMachine($unit, $month, $year);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $refs = array_map(fn (array $row): array => ['id' => $row['machine']['id']], $kinerja);
        $gangguanSheet = $this->mesin->record($unit, MesinHarianJenis::KaliGangguan, $month, $year);
        $kali = $this->mesin->summarize(MesinHarianJenis::KaliGangguan, $this->mesin->sanitize(MesinHarianJenis::KaliGangguan, $gangguanSheet?->readings ?? [], $refs, $daysInMonth), $refs, $daysInMonth)['by_machine'];
        $overrides = $this->record($unit, $month, $year)?->overrides ?? [];

        $rows = array_map(function (array $row) use ($jamPeriode, $produksi, $liters, $kali, $overrides): array {
            $id = $row['machine']['id'];
            $v = $row['values'];
            $terpasang = (float) $v['daya_terpasang'];
            $operasi = (float) $v['jam_operasi'];
            $har = (float) $v['jam_har'];
            $gangguan = (float) $v['jam_gangguan'];
            $kwh = (float) ($produksi[$id] ?? 0);
            $bbm = (float) ($liters[$id] ?? 0);
            $pct = fn (float $a, float $b): float => $b > 0 ? round($a / $b * 100, 2) : 0.0;

            $fof = $pct($gangguan, $jamPeriode);
            $pof = $pct($har, $jamPeriode);
            $for = $pct($gangguan, $gangguan + $operasi);
            $auto = [
                'cf' => $pct($kwh, $terpasang * $jamPeriode),
                'of' => $pct($kwh, $terpasang * $operasi),
                'sf' => $pct($operasi, $jamPeriode),
                'eaf' => round(100 - $fof - $pof, 2),
                'pof' => $pof,
                'fof' => $fof,
                'for' => $for,
                'sof' => $pof,
                'efor' => $for,
                'sdof' => (float) ($kali[$id] ?? 0),
                'eff' => $pct($kwh * self::KCAL_PER_KWH, $bbm * self::KCAL_PER_LITER * self::DENSITY),
            ];

            $own = $overrides[$id] ?? $overrides[(string) $id] ?? [];
            $values = $auto;
            $edited = [];
            foreach (self::FIELDS as $field) {
                if (isset($own[$field]) && $own[$field] !== '') {
                    $values[$field] = (float) $own[$field];
                    $edited[] = $field;
                }
            }

            return [
                'machine' => $row['machine'],
                'operating' => $v['kondisi'] !== 'Tidak Operasi',
                'kondisi' => $v['kondisi'],
                'source' => ['kwh' => $kwh, 'bbm_liter' => $bbm, 'jam_operasi' => $operasi, 'jam_har' => $har, 'jam_gangguan' => $gangguan, 'daya_terpasang' => $terpasang],
                'auto' => $auto,
                'values' => $values,
                'edited' => $edited,
            ];
        }, $kinerja);

        $operating = array_values(array_filter($rows, fn (array $row): bool => $row['operating']));
        $averages = [];
        foreach (self::FIELDS as $field) {
            $averages[$field] = $operating === [] ? 0.0 : round(array_sum(array_map(fn (array $row): float => $row['values'][$field], $operating)) / count($operating), 2);
        }

        return [
            'rows' => $rows,
            'averages' => $averages,
            'jam_periode' => $jamPeriode,
            'constants' => ['kcal_per_kwh' => self::KCAL_PER_KWH, 'kcal_per_liter' => self::KCAL_PER_LITER, 'density' => self::DENSITY],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array<string, float>>
     */
    public function overridesFrom(array $input, array $rows): array
    {
        $overrides = [];
        foreach ($rows as $row) {
            $id = $row['machine']['id'];
            foreach (self::FIELDS as $field) {
                $value = $input[$id][$field] ?? $input[(string) $id][$field] ?? null;
                if ($value !== null && $value !== '' && abs((float) $value - $row['auto'][$field]) > 0.0001) {
                    $overrides[$id][$field] = round((float) $value, 2);
                }
            }
        }

        return $overrides;
    }

    /**
     * Liters of BBM (all jenis) per machine in the month's Pemakaian Bahan Bakar.
     *
     * @return array<int, float>
     */
    private function litersByMachine(Unit $unit, int $month, int $year): array
    {
        $record = OperasiPemakaianBbm::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();
        $liters = [];
        foreach ($record?->raw_readings ?? [] as $key => $days) {
            $machineId = (int) substr((string) $key, (int) strrpos((string) $key, '_') + 1);
            $liters[$machineId] = ($liters[$machineId] ?? 0) + array_sum(array_map('floatval', (array) $days));
        }

        return $liters;
    }
}
