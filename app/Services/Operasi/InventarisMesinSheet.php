<?php

namespace App\Services\Operasi;

use App\Enums\MesinHarianJenis;
use App\Models\Machine;
use App\Models\OperasiRekap;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Daftar Inventarisasi Mesin: the machine master of the unit (penggerak and
 * generator data) with the month's daya mampu and beban tertinggi taken from
 * Beban Tertinggi. Mampu and beban may be corrected and each machine has a
 * keterangan; only those are stored (operasi_rekaps, jenis "inventaris").
 */
class InventarisMesinSheet
{
    public const JENIS = 'inventaris';

    public function __construct(private readonly MesinHarianSheet $mesin) {}

    public function record(Unit $unit, int $month, int $year): ?OperasiRekap
    {
        return OperasiRekap::query()->where('unit_id', $unit->id)->where('jenis', self::JENIS)->where('month', $month)->where('year', $year)->first();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array{terpasang: float, mampu: float, beban: float}}
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get();
        $refs = array_map(fn (Machine $m): array => ['id' => $m->id, 'capacity_kw' => $m->capacity_kw === null ? null : (float) $m->capacity_kw], $machines->all());
        $mampu = $this->mesin->dayaMampu($unit, $month, $year, $refs);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $bebanSheet = $this->mesin->record($unit, MesinHarianJenis::BebanTinggi, $month, $year);
        $beban = $this->mesin->summarize(MesinHarianJenis::BebanTinggi, $this->mesin->sanitize(MesinHarianJenis::BebanTinggi, $bebanSheet?->readings ?? [], $refs, $daysInMonth), $refs, $daysInMonth)['by_machine'];
        $overrides = $this->record($unit, $month, $year)?->overrides ?? [];

        $rows = $machines->values()->map(function (Machine $machine, int $index) use ($mampu, $beban, $overrides): array {
            $own = $overrides[$machine->id] ?? $overrides[(string) $machine->id] ?? [];
            $auto = ['mampu' => (float) ($mampu[$machine->id] ?? 0), 'beban' => (float) ($beban[$machine->id] ?? 0)];

            return [
                'no' => $index + 1,
                'machine' => [
                    'id' => $machine->id,
                    'name' => $machine->name,
                    'merk' => $machine->merk,
                    'type' => $machine->type,
                    'serial_number' => $machine->serial_number,
                    'engine_hp' => $machine->engine_hp,
                    'engine_rpm' => $machine->engine_rpm,
                    'tahun_pembuatan' => $machine->tahun_pembuatan,
                    'generator_merk' => $machine->generator_merk,
                    'generator_type' => $machine->generator_type,
                    'generator_serial_number' => $machine->generator_serial_number,
                    'generator_volt' => $machine->generator_volt,
                    'generator_kva' => $machine->generator_kva === null ? null : (float) $machine->generator_kva,
                    'generator_cos_phi' => $machine->generator_cos_phi === null ? null : (float) $machine->generator_cos_phi,
                    'terpasang' => (float) ($machine->capacity_kw ?? 0),
                ],
                'auto' => $auto,
                'mampu' => isset($own['mampu']) ? (float) $own['mampu'] : $auto['mampu'],
                'beban' => isset($own['beban']) ? (float) $own['beban'] : $auto['beban'],
                'ket' => (string) ($own['ket'] ?? ''),
            ];
        })->all();

        return [
            'rows' => $rows,
            'totals' => [
                'terpasang' => round(array_sum(array_map(fn (array $row): float => $row['machine']['terpasang'], $rows)), 2),
                'mampu' => round(array_sum(array_column($rows, 'mampu')), 2),
                'beban' => round(array_sum(array_column($rows, 'beban')), 2),
            ],
        ];
    }

    /**
     * Keep corrected mampu / beban (different from the automatic value) and
     * non-empty keterangan.
     *
     * @param  array<array-key, mixed>  $input
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function overridesFrom(array $input, array $rows): array
    {
        $overrides = [];
        foreach ($rows as $row) {
            $id = $row['machine']['id'];
            $own = $input[$id] ?? $input[(string) $id] ?? [];

            foreach (['mampu', 'beban'] as $field) {
                if (isset($own[$field]) && $own[$field] !== '' && abs((float) $own[$field] - $row['auto'][$field]) > 0.0001) {
                    $overrides[$id][$field] = round((float) $own[$field], 2);
                }
            }

            if (filled($own['ket'] ?? null)) {
                $overrides[$id]['ket'] = trim((string) $own['ket']);
            }
        }

        return $overrides;
    }
}
