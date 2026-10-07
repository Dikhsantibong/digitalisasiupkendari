<?php

namespace App\Services\Operasi;

use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * The jenis BBM a unit uses — data, not code: the Jenis BBM (master
 * "Jenis BBM") held by the unit's active Tangki BBM (master "Tangki BBM",
 * one unit's tanks may hold HSD, B30, MFO …). A tank not yet linked to a
 * Jenis BBM falls back to the Jenis BBM whose code matches its group. Every
 * active machine of the unit is listed under each jenis. Used by Stand Flow
 * Meter, Pemakaian Bahan Bakar, TUG BBM and Ikhtisar Sentral.
 */
class UnitFuelTypes
{
    /**
     * @return list<array{code: string, name: string, material_code: string|null, machines: list<array{id: int, name: string, type: string|null, serial_number: string|null}>}>
     */
    public function forUnit(Unit $unit): array
    {
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'serial_number'])
            ->map(fn (Machine $machine): array => [
                'id' => $machine->id,
                'name' => $machine->name,
                'type' => $machine->type,
                'serial_number' => $machine->serial_number,
            ])
            ->all();

        return $this->bbmTypes($unit)
            ->map(fn (BbmType|array $bbm): array => [
                'code' => is_array($bbm) ? $bbm['code'] : $bbm->code,
                'name' => is_array($bbm) ? $bbm['name'] : $bbm->name,
                'material_code' => is_array($bbm) ? null : $bbm->material_code,
                'machines' => $machines,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function codes(Unit $unit): array
    {
        return array_column($this->forUnit($unit), 'code');
    }

    /**
     * The distinct Jenis BBM of the unit's active tanks, in the master order.
     *
     * @return Collection<int, BbmType|array{code: string, name: string}>
     */
    private function bbmTypes(Unit $unit): Collection
    {
        $tanks = FuelTank::query()->with('bbmType')->where('unit_id', $unit->id)->where('is_active', true)->get();
        $master = BbmType::query()->get()->keyBy(fn (BbmType $bbm): string => strtoupper($bbm->code));

        return $tanks
            ->map(function (FuelTank $tank) use ($master): BbmType|array {
                if ($tank->bbmType !== null) {
                    return $tank->bbmType;
                }

                $group = $tank->fuel_type->label();

                return $master[strtoupper($group)] ?? ['code' => $group, 'name' => $group];
            })
            ->unique(fn (BbmType|array $bbm): string => strtoupper(is_array($bbm) ? $bbm['code'] : $bbm->code))
            ->sortBy(fn (BbmType|array $bbm): array => is_array($bbm) ? [PHP_INT_MAX, $bbm['code']] : [$bbm->sort_order, $bbm->code])
            ->values();
    }
}
