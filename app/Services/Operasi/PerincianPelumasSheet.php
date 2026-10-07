<?php

namespace App\Services\Operasi;

use App\Enums\PersediaanJenis;
use App\Models\Machine;
use App\Models\OperasiPemakaianPelumas;
use App\Models\OperasiRekap;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Perincian Minyak Pelumas: the month of every jenis pelumas of the unit on
 * one page, as the field Excel:
 *
 * I Persediaan Awal, II Penerimaan (one line per tanggal received) and III
 * Pengembalian, IV Total Persediaan, V Pemakaian Mesin (per mesin) and
 * Pemakaian Non Mesin, VI Pengiriman ke Unit (PLTD / Area / Lisdes, plus the
 * TUG 8 / TUG 10 / Over Flow of Persediaan Pelumas), VII Sisa sesuai
 * perhitungan, VIII Sisa fisik (typed here, else the liters counted in the
 * BA Pemeriksaan Fisik Pelumas, else the computed sisa) and IX Selisih
 * (fisik − perhitungan).
 *
 * Persediaan awal, penerimaan and the TUG / over flow outflows come from
 * Persediaan Pelumas, pemakaian per mesin from Pemakaian Pelumas. Only what
 * no other sheet holds is stored, as an {@see OperasiRekap}: the asal of each
 * penerimaan, pengembalian, pemakaian non mesin, pengiriman ke unit and the
 * physical stock.
 */
class PerincianPelumasSheet
{
    public const JENIS = 'perincian-pelumas';

    /** @var array<string, string> pengiriman line => label */
    public const PENGIRIMAN = ['pltd' => 'PLTD', 'area' => 'Area', 'lisdes' => 'Lisdes'];

    public function __construct(private readonly PersediaanSheet $persediaan) {}

    /**
     * @param  string  $rekapJenis  the OperasiRekap holding the typed-in part: this sheet, or Rekap Pelumas (whose
     *                              typed-in part {@see RekapPelumasSheet} reads itself; `manual` is then empty)
     * @return array{
     *     lubricants: list<array{key: string, name: string, code: string|null, unit_label: string, unit_of_measure: string}>,
     *     machines: list<array{id: int, name: string, merk: string|null, type: string|null, serial_number: string|null}>,
     *     auto: array{
     *         awal: array<string, float>,
     *         penerimaan: list<array{day: int, amounts: array<string, float>}>,
     *         kirim_persediaan: array<string, float>,
     *         pemakaian: array<int, array<string, float>>,
     *         ba_fisik: array<string, float>
     *     },
     *     manual: array{} | array{
     *         asal: array<int, string>,
     *         pengembalian: array{tanggal: string|null, amounts: array<string, float>},
     *         non_mesin: array<string, float>,
     *         pengiriman: array<string, array{tanggal: string|null, amounts: array<string, float>}>,
     *         fisik: array<string, float>
     *     },
     *     record: OperasiRekap|null
     * }
     */
    public function build(Unit $unit, int $month, int $year, string $rekapJenis = self::JENIS): array
    {
        $jenis = PersediaanJenis::Pelumas;
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $lubricants = $this->persediaan->items($unit, $jenis);
        $keys = array_column($lubricants, 'key');
        $persediaanRecord = $this->persediaan->record($unit, $jenis, $month, $year);
        $opening = $this->persediaan->openingOf($persediaanRecord, $this->persediaan->carriedOpening($unit, $jenis, $month, $year, $lubricants), $lubricants);
        $sheet = $this->persediaan->compute($jenis, $lubricants, $opening, $persediaanRecord?->entries ?? [], $this->persediaan->pemakaian($unit, $jenis, $month, $year, $lubricants), $daysInMonth);

        $penerimaan = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $amounts = [];
            foreach ($keys as $key) {
                $amounts[$key] = (float) $sheet[$key]['rows'][$day]['penerimaan'];
            }
            if (array_sum($amounts) > 0) {
                $penerimaan[] = ['day' => $day, 'amounts' => $amounts];
            }
        }

        $kirim = [];
        foreach ($keys as $key) {
            $kirim[$key] = round(array_sum(array_map(
                fn (string $field): float => (float) $sheet[$key]['total'][$field],
                array_keys($this->persediaan->kirimColumns($jenis)),
            )), 2);
        }

        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'merk', 'type', 'serial_number']);
        $readings = OperasiPemakaianPelumas::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first()?->raw_readings ?? [];
        $pemakaian = [];
        foreach ($machines as $machine) {
            foreach ($keys as $key) {
                $pemakaian[$machine->id][$key] = round(array_sum(array_map('floatval', $readings["{$key}_{$machine->id}"] ?? [])), 2);
            }
        }

        $record = OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', $rekapJenis)->where('month', $month)->where('year', $year)->first();

        // The liters counted in the BA Pemeriksaan Fisik Pelumas are the default sisa fisik.
        $counted = OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', BaFisikPelumasSheet::JENIS)->where('month', $month)->where('year', $year)->first()?->overrides['items'] ?? [];
        $baFisik = [];
        foreach ($keys as $key) {
            if (is_numeric($counted[$key]['liter'] ?? null)) {
                $baFisik[$key] = (float) $counted[$key]['liter'];
            }
        }

        return [
            'lubricants' => $lubricants,
            'machines' => $machines->map(fn (Machine $machine): array => ['id' => $machine->id, 'name' => $machine->name, 'merk' => $machine->merk, 'type' => $machine->type, 'serial_number' => $machine->serial_number])->all(),
            'auto' => [
                'awal' => $opening,
                'penerimaan' => $penerimaan,
                'kirim_persediaan' => $kirim,
                'pemakaian' => $pemakaian,
                'ba_fisik' => $baFisik,
            ],
            'manual' => $rekapJenis === self::JENIS ? $this->sanitize($record?->overrides ?? [], $keys, $daysInMonth) : [],
            'record' => $record,
        ];
    }

    /**
     * Keep only the typed-in parts of the sheet, for these jenis pelumas.
     *
     * @param  array<array-key, mixed>  $input
     * @param  list<string>  $keys
     * @return array{asal: array<int, string>, pengembalian: array{tanggal: string|null, amounts: array<string, float>}, non_mesin: array<string, float>, pengiriman: array<string, array{tanggal: string|null, amounts: array<string, float>}>, fisik: array<string, float>}
     */
    public function sanitize(array $input, array $keys, int $daysInMonth): array
    {
        $amounts = function (mixed $values) use ($keys): array {
            $clean = [];
            foreach ($keys as $key) {
                $value = is_array($values) ? ($values[$key] ?? null) : null;
                if (is_numeric($value) && (float) $value >= 0) {
                    $clean[$key] = round((float) $value, 2);
                }
            }

            return $clean;
        };
        $line = fn (mixed $value): array => [
            'tanggal' => is_array($value) && filled($value['tanggal'] ?? null) ? (string) $value['tanggal'] : null,
            'amounts' => array_filter($amounts(is_array($value) ? ($value['amounts'] ?? []) : []), fn (float $amount): bool => $amount > 0),
        ];

        $asal = [];
        foreach ((array) ($input['asal'] ?? []) as $day => $label) {
            if ((int) $day >= 1 && (int) $day <= $daysInMonth && filled($label)) {
                $asal[(int) $day] = mb_substr(trim((string) $label), 0, 100);
            }
        }

        $pengiriman = [];
        foreach (array_keys(self::PENGIRIMAN) as $target) {
            $pengiriman[$target] = $line($input['pengiriman'][$target] ?? null);
        }

        return [
            'asal' => $asal,
            'pengembalian' => $line($input['pengembalian'] ?? null),
            'non_mesin' => array_filter($amounts($input['non_mesin'] ?? []), fn (float $amount): bool => $amount > 0),
            'pengiriman' => $pengiriman,
            // A physical stock of 0 is a real count, so it is kept.
            'fisik' => $amounts($input['fisik'] ?? []),
        ];
    }

    /**
     * The Roman-numbered totals, per jenis pelumas and for all of them
     * (`total`).
     *
     * @param  array{lubricants: list<array{key: string}>, auto: array<string, mixed>, manual: array<string, mixed>}  $data
     * @return array<string, array<string, float>>
     */
    public function totals(array $data): array
    {
        $keys = array_column($data['lubricants'], 'key');
        $auto = $data['auto'];
        $manual = $data['manual'];
        $rows = [];

        foreach ($keys as $key) {
            $penerimaan = array_sum(array_map(fn (array $line): float => (float) ($line['amounts'][$key] ?? 0), $auto['penerimaan']));
            $jumlahPenerimaan = $penerimaan + (float) ($manual['pengembalian']['amounts'][$key] ?? 0);
            $totalPersediaan = (float) ($auto['awal'][$key] ?? 0) + $jumlahPenerimaan;
            $jumlahPemakaian = array_sum(array_map(fn (array $machine): float => (float) ($machine[$key] ?? 0), $auto['pemakaian']));
            $jumlahPengiriman = (float) ($auto['kirim_persediaan'][$key] ?? 0)
                + array_sum(array_map(fn (array $line): float => (float) ($line['amounts'][$key] ?? 0), $manual['pengiriman']));
            $sisa = $totalPersediaan - $jumlahPemakaian - (float) ($manual['non_mesin'][$key] ?? 0) - $jumlahPengiriman;
            $fisik = (float) ($manual['fisik'][$key] ?? $auto['ba_fisik'][$key] ?? $sisa);

            $rows[$key] = array_map(fn (float $value): float => round($value, 2), [
                'awal' => (float) ($auto['awal'][$key] ?? 0),
                'jumlah_penerimaan' => $jumlahPenerimaan,
                'total_persediaan' => $totalPersediaan,
                'jumlah_pemakaian' => $jumlahPemakaian,
                'non_mesin' => (float) ($manual['non_mesin'][$key] ?? 0),
                'jumlah_pengiriman' => $jumlahPengiriman,
                'sisa' => $sisa,
                'fisik' => $fisik,
                'selisih' => $fisik - $sisa,
            ]);
        }

        $total = [];
        foreach (['awal', 'jumlah_penerimaan', 'total_persediaan', 'jumlah_pemakaian', 'non_mesin', 'jumlah_pengiriman', 'sisa', 'fisik', 'selisih'] as $field) {
            $total[$field] = round(array_sum(array_column($rows, $field)), 2);
        }

        return $rows + ['total' => $total];
    }
}
