<?php

namespace App\Services\Operasi;

use App\Enums\JamMesinJenis;
use App\Models\OperasiRekap;
use App\Models\Unit;

/**
 * Kinerja Unit Mesin (Pengusahaan Pembangkit) — not typed in: every value is
 * taken from the other sheets and may be corrected per machine:
 *
 * - daya terpasang: Master Mesin (capacity);
 * - daya mampu: Beban Tertinggi (daya mampu of the month);
 * - kondisi: "Beroperasi" when the machine has jam operasi, else "Tidak Operasi";
 * - jam operasi / har / gangguan: the month totals of the Jam sheets;
 * - ratio daya pembangkit = daya mampu ÷ daya terpasang × 100.
 *
 * The unit ratio is the average ratio of the machines "Beroperasi".
 */
class KinerjaMesinSheet
{
    public const JENIS = 'kinerja';

    public const KONDISI = ['Beroperasi', 'Tidak Operasi', 'Pemeliharaan', 'Gangguan'];

    /** @var list<string> */
    public const FIELDS = ['daya_terpasang', 'daya_mampu', 'kondisi', 'kode_kondisi', 'jam_operasi', 'jam_har', 'jam_gangguan'];

    public function __construct(
        private readonly MesinHarianSheet $mesin,
        private readonly JamMesinSheet $jam,
    ) {}

    public function record(Unit $unit, int $month, int $year): ?OperasiRekap
    {
        return OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', self::JENIS)->where('month', $month)->where('year', $year)->first();
    }

    /**
     * @return array{
     *     rows: list<array{machine: array<string, mixed>, auto: array<string, mixed>, values: array<string, mixed>, edited: list<string>, ratio: float}>,
     *     totals: array{daya_terpasang: float, daya_mampu: float, jam_operasi: float, jam_har: float, jam_gangguan: float, ratio: float}
     * }
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $machines = $this->mesin->machines($unit);
        $mampu = $this->mesin->dayaMampu($unit, $month, $year, $machines);
        $hours = [];
        foreach (JamMesinJenis::cases() as $jenis) {
            $hours[$jenis->value] = $this->jam->summarize($this->jam->readings($unit, $jenis, $month, $year, $machines), $machines)['totals_by_machine'];
        }

        $overrides = $this->record($unit, $month, $year)?->overrides ?? [];

        $rows = array_map(function (array $machine) use ($mampu, $hours, $overrides): array {
            $jamOperasi = (float) ($hours['operasi'][$machine['id']] ?? 0);
            $auto = [
                'daya_terpasang' => (float) ($machine['capacity_kw'] ?? 0),
                'daya_mampu' => (float) ($mampu[$machine['id']] ?? 0),
                'kondisi' => $jamOperasi > 0 ? 'Beroperasi' : 'Tidak Operasi',
                'kode_kondisi' => '',
                'jam_operasi' => $jamOperasi,
                'jam_har' => (float) ($hours['pemeliharaan'][$machine['id']] ?? 0),
                'jam_gangguan' => (float) ($hours['gangguan'][$machine['id']] ?? 0),
            ];

            $own = $overrides[$machine['id']] ?? $overrides[(string) $machine['id']] ?? [];
            $values = $auto;
            $edited = [];
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $own) && $own[$field] !== null) {
                    $values[$field] = in_array($field, ['kondisi', 'kode_kondisi'], true) ? (string) $own[$field] : (float) $own[$field];
                    $edited[] = $field;
                }
            }

            return [
                'machine' => $machine,
                'auto' => $auto,
                'values' => $values,
                'edited' => $edited,
                'ratio' => $values['daya_terpasang'] > 0 ? round($values['daya_mampu'] / $values['daya_terpasang'] * 100, 3) : 0.0,
            ];
        }, $machines);

        $operating = array_filter($rows, fn (array $row): bool => $row['values']['kondisi'] === 'Beroperasi');
        $sum = fn (string $field): float => round(array_sum(array_map(fn (array $row): float => (float) $row['values'][$field], $rows)), 2);

        return [
            'rows' => $rows,
            'totals' => [
                'daya_terpasang' => $sum('daya_terpasang'),
                'daya_mampu' => $sum('daya_mampu'),
                'jam_operasi' => $sum('jam_operasi'),
                'jam_har' => $sum('jam_har'),
                'jam_gangguan' => $sum('jam_gangguan'),
                'ratio' => $operating === [] ? 0.0 : round(array_sum(array_column($operating, 'ratio')) / count($operating), 2),
            ],
        ];
    }

    /**
     * Keep only real corrections: values that differ from the automatic one.
     *
     * @param  array<array-key, mixed>  $input
     * @param  list<array{machine: array<string, mixed>, auto: array<string, mixed>}>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function overridesFrom(array $input, array $rows): array
    {
        $overrides = [];
        foreach ($rows as $row) {
            $id = $row['machine']['id'];
            foreach (self::FIELDS as $field) {
                $value = $input[$id][$field] ?? $input[(string) $id][$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                $text = in_array($field, ['kondisi', 'kode_kondisi'], true);
                $value = $text ? trim((string) $value) : round((float) $value, 2);
                if ($text ? $value !== (string) $row['auto'][$field] : abs($value - (float) $row['auto'][$field]) > 0.0001) {
                    $overrides[$id][$field] = $value;
                }
            }
        }

        return $overrides;
    }
}
