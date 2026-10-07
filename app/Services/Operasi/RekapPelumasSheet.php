<?php

namespace App\Services\Operasi;

use App\Models\OperasiRekap;
use App\Models\Unit;

/**
 * Rekap Pelumas — "Pemakaian Pelumas & Grease", as the field Excel: per jenis
 * pelumas the persediaan awal, penerimaan and total; (A) pemakaian per mesin;
 * (B) pemakaian alat bantu (12 lines, the first being the pengiriman TUG 8 /
 * TUG 10 / over flow of Persediaan Pelumas); TOTAL (A+B); sisa persediaan
 * akhir menurut kartu and fisik; selisih (fisik − kartu). The TOTAL (LTR)
 * PELUMAS column sums the jenis counted in liter / drum, TOTAL (KG) GREASE
 * the ones counted in kg.
 *
 * Shares the automatic part with {@see PerincianPelumasSheet}; only the alat
 * bantu amounts and the physical stock are stored (OperasiRekap
 * `rekap-pelumas`).
 */
class RekapPelumasSheet
{
    public const JENIS = 'rekap-pelumas';

    /** @var array<string, string> alat bantu line => label (the first is filled from Persediaan Pelumas) */
    public const ALAT_BANTU = [
        'pengiriman' => 'Pengiriman (TUG 8)',
        'pompa_jw_cw' => 'Pompa JW/CW',
        'compressor_caterpillar' => 'Compressor Caterpillar',
        'compressor_daihatsu' => 'Compressor Daihatsu',
        'compressor_mak' => 'Compressor MAK',
        'turbocharger' => 'Turbocharger',
        'governor' => 'Governor',
        'bearing_generator' => 'Bearing Generator',
        'oil_bath_air_filter' => 'Oil Bath Air Filter Daihatsu',
        'pompa_hidraulic' => 'Pompa Hidraulic',
        'incenerator' => 'Pemakaian Incenerator',
        'lain_lain' => 'Lain-lain / Compressor HAR',
    ];

    public function __construct(private readonly PerincianPelumasSheet $perincian) {}

    /**
     * @return array{
     *     lubricants: list<array{key: string, name: string, code: string|null, unit_label: string, unit_of_measure: string}>,
     *     machines: list<array{id: int, name: string, merk: string|null, type: string|null, serial_number: string|null}>,
     *     auto: array<string, mixed>,
     *     manual: array{alat_bantu: array<string, array<string, float>>, fisik: array<string, float>},
     *     record: OperasiRekap|null
     * }
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $data = $this->perincian->build($unit, $month, $year, self::JENIS);
        $data['manual'] = $this->sanitize($data['record']?->overrides ?? [], array_column($data['lubricants'], 'key'));

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @param  list<string>  $keys
     * @return array{alat_bantu: array<string, array<string, float>>, fisik: array<string, float>}
     */
    public function sanitize(array $input, array $keys): array
    {
        $amounts = function (mixed $values, bool $keepZero) use ($keys): array {
            $clean = [];
            foreach ($keys as $key) {
                $value = is_array($values) ? ($values[$key] ?? null) : null;
                if (is_numeric($value) && ((float) $value > 0 || ($keepZero && (float) $value === 0.0))) {
                    $clean[$key] = round((float) $value, 2);
                }
            }

            return $clean;
        };

        $alatBantu = [];
        foreach (array_keys(self::ALAT_BANTU) as $line) {
            // The pengiriman line is read from Persediaan Pelumas, never typed.
            if ($line !== 'pengiriman' && ($values = $amounts($input['alat_bantu'][$line] ?? [], false)) !== []) {
                $alatBantu[$line] = $values;
            }
        }

        // A physical stock of 0 is a real count, so it is kept.
        return ['alat_bantu' => $alatBantu, 'fisik' => $amounts($input['fisik'] ?? [], true)];
    }

    /**
     * Per jenis pelumas and for the PELUMAS (liter / drum) and GREASE (kg)
     * total columns.
     *
     * @param  array{lubricants: list<array{key: string, unit_of_measure: string}>, auto: array<string, mixed>, manual: array{alat_bantu: array<string, array<string, float>>, fisik: array<string, float>}}  $data
     * @return array<string, array<string, float>>
     */
    public function totals(array $data): array
    {
        $auto = $data['auto'];
        $manual = $data['manual'];
        $rows = [];

        foreach ($data['lubricants'] as $lubricant) {
            $key = $lubricant['key'];
            $penerimaan = array_sum(array_map(fn (array $line): float => (float) ($line['amounts'][$key] ?? 0), $auto['penerimaan']));
            $total = (float) ($auto['awal'][$key] ?? 0) + $penerimaan;
            $mesin = array_sum(array_map(fn (array $machine): float => (float) ($machine[$key] ?? 0), $auto['pemakaian']));
            $alatBantu = (float) ($auto['kirim_persediaan'][$key] ?? 0)
                + array_sum(array_map(fn (array $line): float => (float) ($line[$key] ?? 0), $manual['alat_bantu']));
            $kartu = $total - $mesin - $alatBantu;
            $fisik = (float) ($manual['fisik'][$key] ?? $auto['ba_fisik'][$key] ?? $kartu);

            $rows[$key] = array_map(fn (float $value): float => round($value, 2), [
                'awal' => (float) ($auto['awal'][$key] ?? 0),
                'penerimaan' => $penerimaan,
                'total' => $total,
                'mesin' => $mesin,
                'alat_bantu' => $alatBantu,
                'pemakaian' => $mesin + $alatBantu,
                'kartu' => $kartu,
                'fisik' => $fisik,
                'selisih' => $fisik - $kartu,
            ]);
        }

        $sum = function (bool $grease) use ($data, $rows): array {
            $keys = array_column(array_filter($data['lubricants'], fn (array $l): bool => ($l['unit_of_measure'] === 'kg') === $grease), 'key');
            $total = [];
            foreach (['awal', 'penerimaan', 'total', 'mesin', 'alat_bantu', 'pemakaian', 'kartu', 'fisik', 'selisih'] as $field) {
                $total[$field] = round(array_sum(array_map(fn (string $key): float => $rows[$key][$field], $keys)), 2);
            }

            return $total;
        };

        return $rows + ['pelumas' => $sum(false), 'grease' => $sum(true)];
    }
}
