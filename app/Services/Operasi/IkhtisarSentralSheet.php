<?php

namespace App\Services\Operasi;

use App\Enums\JamMesinJenis;
use App\Enums\MesinHarianJenis;
use App\Models\FuelReceipt;
use App\Models\LubricantReceipt;
use App\Models\OperasiIkhtisarSentral;
use App\Models\OperasiPemakaianBbm;
use App\Models\OperasiPemakaianPelumas;
use App\Models\OperasiRekap;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Ikhtisar Sentral is not typed in: its figures come from the other
 * Pengusahaan Operasi sheets, and any of them may be corrected (the
 * correction is kept, keyed by its path, in operasi_rekaps "ikhtisar-sentral").
 *
 * - kWh dibangkit & PS per mesin / unit: Stand kWh Harian;
 * - jam jalan: Jam Operasi; beban puncak malam: Beban Tertinggi (unit peak);
 * - pemakaian BBM per jenis BBM of the unit (master Tangki BBM → Jenis BBM):
 *   Pemakaian Bahan Bakar; T. kalor = liter × 10.289 ×
 *   0,949 / kWh (kkal/kWh, as EFF in Kinerja Pembangkit Termal);
 * - pemakaian pelumas: Pemakaian Pelumas;
 * - penerimaan BBM / pelumas: Penerimaan BBM & pelumas of the month;
 * - persediaan awal: last month's persediaan akhir.
 *
 * Beban puncak pagi, jam jalan per hari, sewa SMP, pemakaian non operasi and
 * pengiriman stay manual.
 */
class IkhtisarSentralSheet
{
    public const JENIS = 'ikhtisar-sentral';

    public function __construct(
        private readonly KwhSheet $kwh,
        private readonly JamMesinSheet $jam,
        private readonly MesinHarianSheet $mesin,
        private readonly UnitFuelTypes $fuels,
    ) {}

    /**
     * The unit's jenis BBM (master), the BBM columns of the sheet.
     *
     * @return list<array{code: string, name: string}>
     */
    public function fuels(Unit $unit): array
    {
        return array_map(fn (array $fuel): array => ['code' => $fuel['code'], 'name' => $fuel['name']], $this->fuels->forUnit($unit));
    }

    /**
     * The automatic value of every computed field, by path.
     *
     * @param  list<array{id: int}>  $machines
     * @param  list<array{id: int}>  $lubricants
     * @return array<string, float>
     */
    public function auto(Unit $unit, int $month, int $year, array $machines, array $lubricants): array
    {
        $auto = [];
        $harian = collect($this->kwh->harian($unit, $month, $year))->keyBy('machine.id');
        $refs = array_map(fn (array $m): array => ['id' => $m['id']], $machines);
        $jamOperasi = $this->jam->summarize($this->jam->readings($unit, JamMesinJenis::Operasi, $month, $year, $refs), $refs)['totals_by_machine'];
        $bbm = OperasiPemakaianBbm::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first()?->raw_readings ?? [];
        $pelumas = OperasiPemakaianPelumas::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first()?->raw_readings ?? [];
        $sum = fn (mixed $days): float => round(array_sum(array_map('floatval', (array) $days)), 2);
        $codes = array_column($this->fuels($unit), 'code');

        $ps = 0.0;
        foreach ($machines as $machine) {
            $id = $machine['id'];
            $kwh = (float) ($harian[$id]['totals']['produksi'] ?? 0);
            $liters = 0.0;
            foreach ($codes as $code) {
                $auto["mesins.{$id}.bbm.{$code}"] = $sum($bbm["{$code}_{$id}"] ?? []);
                $liters += $auto["mesins.{$id}.bbm.{$code}"];
            }
            $ps += (float) ($harian[$id]['totals']['ps'] ?? 0);

            $auto["mesins.{$id}.kwh_dibangkit"] = round($kwh, 2);
            $auto["mesins.{$id}.jam_jalan"] = (float) ($jamOperasi[$id] ?? 0);
            $auto["mesins.{$id}.t_kalor"] = $kwh > 0 ? round($liters * KinerjaTermalSheet::KCAL_PER_LITER * KinerjaTermalSheet::DENSITY / $kwh, 2) : 0.0;
            foreach ($lubricants as $lubricant) {
                $auto["mesins.{$id}.pemakaian_pelumas.{$lubricant['id']}"] = $sum($pelumas["{$lubricant['id']}_{$id}"] ?? []);
            }
        }

        $auto['summary.kwh_pemakaian_sendiri'] = round($ps, 2);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $bebanSheet = $this->mesin->record($unit, MesinHarianJenis::BebanTinggi, $month, $year);
        $auto['summary.beban_puncak_malam_kw'] = $this->mesin->summarize(MesinHarianJenis::BebanTinggi, $this->mesin->sanitize(MesinHarianJenis::BebanTinggi, $bebanSheet?->readings ?? [], $refs, $daysInMonth), $refs, $daysInMonth)['total'];

        $start = Carbon::create($year, $month, 1);
        $fuel = FuelReceipt::query()->where('unit_id', $unit->id)->whereBetween('report_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->get(['fuel_type', 'volume_liter']);
        // A receipt counts for the jenis BBM whose code matches its BBM group.
        foreach ($codes as $code) {
            $auto["inventory.penerimaan.bbm.{$code}"] = round((float) $fuel->filter(fn (FuelReceipt $r): bool => strcasecmp($r->fuel_type->label(), $code) === 0)->sum('volume_liter'), 2);
        }
        $lubeReceipts = LubricantReceipt::query()->where('unit_id', $unit->id)->whereBetween('report_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->get(['lubricant_type_id', 'volume'])->groupBy('lubricant_type_id');
        foreach ($lubricants as $lubricant) {
            $auto["inventory.penerimaan.lubricants.{$lubricant['id']}"] = round((float) ($lubeReceipts->get($lubricant['id'])?->sum('volume') ?? 0), 2);
        }

        foreach ($this->previousClosing($unit, $month, $year, $lubricants, $codes) as $path => $value) {
            $auto["inventory.persediaan_awal.{$path}"] = $value;
        }

        return $auto;
    }

    /**
     * @return array<string, float>
     */
    public function overrides(Unit $unit, int $month, int $year): array
    {
        return OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', self::JENIS)->where('month', $month)->where('year', $year)->value('overrides') ?? [];
    }

    /**
     * The corrections in a saved payload: computed fields whose value differs
     * from the automatic one.
     *
     * @param  array<string, mixed>  $payload  summary / mesins (by engine id) / inventory
     * @param  array<string, float>  $auto
     * @return array<string, float>
     */
    public function overridesFrom(array $payload, array $auto): array
    {
        $overrides = [];
        foreach ($auto as $path => $value) {
            $posted = data_get($payload, $path);
            if ($posted !== null && $posted !== '' && is_numeric($posted) && abs((float) $posted - $value) > 0.005) {
                $overrides[$path] = round((float) $posted, 2);
            }
        }

        return $overrides;
    }

    /**
     * Last month's persediaan akhir (awal + penerimaan + sewa − pemakaian mesin
     * − non operasi − pengiriman), by "bbm.{code}" and "lubricants.{id}".
     *
     * @param  list<array{id: int}>  $lubricants
     * @param  list<string>  $codes
     * @return array<string, float>
     */
    private function previousClosing(Unit $unit, int $month, int $year, array $lubricants, array $codes): array
    {
        $previous = Carbon::create($year, $month, 1)->subMonth();
        $record = OperasiIkhtisarSentral::query()->with('mesins')->where('unit_id', $unit->id)->where('month', $previous->month)->where('year', $previous->year)->first();

        $closing = [];
        foreach ($codes as $code) {
            $closing["bbm.{$code}"] = 0.0;
        }
        foreach ($lubricants as $lubricant) {
            $closing["lubricants.{$lubricant['id']}"] = 0.0;
        }

        if ($record === null) {
            return $closing;
        }

        $value = fn (?array $row, string $path): float => (float) data_get($row ?? [], $path, 0);
        foreach (array_keys($closing) as $path) {
            [$group, $key] = explode('.', $path, 2);
            $used = (float) $record->mesins->sum(fn ($m): float => (float) data_get(($group === 'bbm' ? $m->pemakaian_bbm : $m->pemakaian_pelumas) ?? [], $key, 0));

            $closing[$path] = round(
                $value($record->persediaan_awal, $path) + $value($record->penerimaan, $path) + $value($record->penerimaan_sewa_smp, $path)
                - $used - $value($record->pemakaian_non_operasi, $path) - $value($record->pengiriman, $path),
                2,
            );
        }

        return $closing;
    }
}
