<?php

namespace App\Services\Operasi;

use App\Enums\TugJenis;
use App\Models\Machine;
use App\Models\OperasiPemakaianBbm;
use App\Models\Unit;

/**
 * TUG 9 BBM — Bon Pemakaian Energi Primer (HSD/MFO/Batubara): one column per
 * jenis BBM the machine burns (material code from the Jenis BBM master), the
 * amounts from Pemakaian Bahan Bakar.
 */
class TugBbmDocument extends TugDocument
{
    public function __construct(private readonly PemakaianBbmSheet $sheet) {}

    public function jenis(): TugJenis
    {
        return TugJenis::Bbm;
    }

    protected function columns(Unit $unit, Machine $machine): array
    {
        return collect($this->sheet->columns($unit))
            ->filter(fn (array $fuel): bool => in_array($machine->id, array_column($fuel['machines'], 'id'), true))
            ->map(fn (array $fuel): array => [
                'key' => $fuel['code'],
                'name' => 'BBM '.$fuel['name'],
                'code' => $fuel['material_code'],
                'unit_label' => 'Liter',
            ])
            ->values()
            ->all();
    }

    protected function source(Unit $unit, Machine $machine, int $month, int $year): array
    {
        $sheet = OperasiPemakaianBbm::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();
        $readings = $sheet?->raw_readings ?? [];

        return [
            'readings' => fn (string $key, int $day): float => (float) ($readings[$this->sheet->key($key, $machine->id)][$day] ?? 0),
            'has_sheet' => $sheet !== null,
        ];
    }
}
