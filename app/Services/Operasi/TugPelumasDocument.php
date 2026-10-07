<?php

namespace App\Services\Operasi;

use App\Enums\TugJenis;
use App\Models\Machine;
use App\Models\OperasiPemakaianPelumas;
use App\Models\Unit;

/**
 * TUG 9 Pelumas — Bon Pemakaian Energi Primer (Pelumas): one column per jenis
 * pelumas the machine uses (material code = the pelumas master code), the
 * amounts from Pemakaian Pelumas.
 */
class TugPelumasDocument extends TugDocument
{
    public function __construct(private readonly PemakaianPelumasSheet $sheet) {}

    public function jenis(): TugJenis
    {
        return TugJenis::Pelumas;
    }

    protected function columns(Unit $unit, Machine $machine): array
    {
        return collect($this->sheet->columns($unit))
            ->filter(fn (array $lubricant): bool => in_array($machine->id, array_column($lubricant['machines'], 'id'), true))
            ->map(fn (array $lubricant): array => [
                'key' => (string) $lubricant['id'],
                'name' => $lubricant['name'],
                'code' => $lubricant['code'],
                'unit_label' => $lubricant['unit_label'],
            ])
            ->values()
            ->all();
    }

    protected function source(Unit $unit, Machine $machine, int $month, int $year): array
    {
        $sheet = OperasiPemakaianPelumas::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();
        $readings = $sheet?->raw_readings ?? [];

        return [
            'readings' => fn (string $key, int $day): float => (float) ($readings[$this->sheet->key((int) $key, $machine->id)][$day] ?? 0),
            'has_sheet' => $sheet !== null,
        ];
    }
}
