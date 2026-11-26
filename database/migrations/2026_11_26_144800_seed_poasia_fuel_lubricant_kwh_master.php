<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Master data of PLTD Poasia & PLTD Poasia Containerized as given by the user
 * (2026-11-26): fuel tanks, BBM types (B50, MFO), lubricants and kWh meters.
 *
 * Idempotent desired state per unit: a row whose old name is known is renamed
 * in place (its id — and so the opname history and stand awal — stays), a
 * missing row is created, and every other active row of the unit in those
 * masters is deactivated (never deleted; it can be switched on again in
 * Master Data). A unit that does not exist is skipped.
 */
return new class extends Migration
{
    private const POASIA = 'PLTD Poasia';

    private const CONTAINERIZED = 'PLTD Poasia Containerized';

    public function up(): void
    {
        $now = now();
        $poasia = $this->unitId(self::POASIA);
        $containerized = $this->unitId(self::CONTAINERIZED);

        // A database without these units (a fresh install, the tests): nothing to fill.
        if ($poasia === null && $containerized === null) {
            return;
        }

        $b50 = $this->bbmType('B50', 'Biosolar B50 (HSD B50)', 'Biodiesel', $now);
        $mfo = $this->bbmType('MFO', 'Marine Fuel Oil', 'MFO', $now);

        if ($poasia !== null) {
            $this->tanks($poasia, [
                ['name' => 'Tangki 1500 KL (HSD B50)', 'from' => 'Tangki HSD 1500 Ton', 'jenis' => 'storage', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 1_500_000, 'daily' => false],
                ['name' => 'Tangki 90 KL (HSD B50)', 'from' => 'Tangki HSD 90 Ton', 'jenis' => 'storage', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 90_000, 'daily' => false],
                ['name' => 'Tangki 33 KL (HSD B50)', 'from' => null, 'jenis' => 'storage', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 33_000, 'daily' => false],
                ['name' => 'Tangki 1500 KL (MFO) #1', 'from' => 'Tangki MFO 1500 Ton (A)', 'jenis' => 'storage', 'bbm' => $mfo, 'fuel' => 'mfo', 'liter' => 1_500_000, 'daily' => false],
                ['name' => 'Tangki 1500 KL (MFO) #2', 'from' => 'Tangki MFO 1500 Ton (B)', 'jenis' => 'storage', 'bbm' => $mfo, 'fuel' => 'mfo', 'liter' => 1_500_000, 'daily' => false],
                ['name' => 'Tangki Harian 3.5 KL (HSD B50) #1', 'from' => 'Tangki Harian HSD 3.5 KL (A)', 'jenis' => 'service', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 3_500, 'daily' => true],
                ['name' => 'Tangki Harian 3.5 KL (HSD B50) #2', 'from' => 'Tangki Harian HSD 3.5 KL (B)', 'jenis' => 'service', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 3_500, 'daily' => true],
            ], $now);

            $this->lubricants($poasia, [
                ['name' => 'Total Aurelia (Mirrlees)', 'from' => 'Total Aurelia TI3030', 'uom' => 'drum'],
            ], $now);

            $machines = $this->machines($poasia);
            $meters = [];

            foreach ([['produksi', 'kWh Bruto', 'kWh Produksi'], ['netto', 'kWh Netto', null], ['ps', 'kWh PS', 'kWh PS']] as [$kategori, $label, $oldLabel]) {
                foreach ($machines as $machine) {
                    $meters[] = ['name' => "{$label} {$machine->name}", 'from' => $oldLabel ? "{$oldLabel} {$machine->name}" : null, 'kategori' => $kategori, 'machine' => $machine->id];
                }
            }

            $this->kwhMeters($poasia, $meters, $now);
        }

        if ($containerized !== null) {
            $this->tanks($containerized, [
                ['name' => 'Tangki Harian 2 KL (HSD B50) #1', 'from' => 'Tangki Harian #1 (HSD)', 'jenis' => 'service', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 2_000, 'daily' => true],
                ['name' => 'Tangki Harian 2 KL (HSD B50) #2', 'from' => null, 'jenis' => 'service', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 2_000, 'daily' => true],
                ['name' => 'Tangki Harian 2 KL (HSD B50) #3', 'from' => null, 'jenis' => 'service', 'bbm' => $b50, 'fuel' => 'hsd', 'liter' => 2_000, 'daily' => true],
            ], $now);

            $this->lubricants($containerized, [
                ['name' => 'Meditran SX Plus CH-4', 'from' => 'Meditran SX 15W/40 CH-4', 'uom' => 'drum'],
                ['name' => 'Trafolube A', 'from' => 'TravoLube A', 'uom' => 'liter'],
            ], $now);

            // "kWh incoming Cummins 6–8": the unit's Cummins in master order (#1, #2, #3) are numbered 6, 7, 8.
            $meters = [];

            foreach ($this->machines($containerized) as $index => $machine) {
                $meters[] = ['name' => 'kWh Incoming Cummins #'.(6 + $index), 'from' => "kWh Produksi {$machine->name}", 'kategori' => 'produksi', 'machine' => $machine->id];
            }

            $this->kwhMeters($containerized, $meters, $now);
        }
    }

    public function down(): void
    {
        // Master data: kept on rollback (rows were renamed / deactivated, never deleted).
    }

    private function unitId(string $name): ?int
    {
        $id = DB::table('units')->where('name', $name)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function bbmType(string $code, string $name, string $category, mixed $now): int
    {
        $id = DB::table('bbm_types')->where('code', $code)->value('id');

        if ($id !== null) {
            DB::table('bbm_types')->where('id', $id)->update(['is_active' => true, 'updated_at' => $now]);

            return (int) $id;
        }

        return (int) DB::table('bbm_types')->insertGetId([
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'sort_order' => (int) DB::table('bbm_types')->max('sort_order') + 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return list<object{id: int, name: string}>
     */
    private function machines(int $unitId): array
    {
        return DB::table('machines')->where('unit_id', $unitId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name'])->all();
    }

    /**
     * The row named `$name` (or, once, the one still named `$from`), renamed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function put(string $table, int $unitId, string $name, ?string $from, array $attributes, mixed $now): int
    {
        $id = DB::table($table)->where('unit_id', $unitId)->where('name', $name)->value('id')
            ?? ($from === null ? null : DB::table($table)->where('unit_id', $unitId)->where('name', $from)->value('id'));

        if ($id !== null) {
            DB::table($table)->where('id', $id)->update(['name' => $name, ...$attributes, 'is_active' => true, 'updated_at' => $now]);

            return (int) $id;
        }

        return (int) DB::table($table)->insertGetId([
            'unit_id' => $unitId,
            'name' => $name,
            ...$attributes,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  list<int>  $keep
     */
    private function deactivateOthers(string $table, int $unitId, array $keep, mixed $now): void
    {
        DB::table($table)->where('unit_id', $unitId)->where('is_active', true)->whereNotIn('id', $keep)
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    /**
     * @param  list<array{name: string, from: string|null, jenis: string, bbm: int, fuel: string, liter: int, daily: bool}>  $tanks
     */
    private function tanks(int $unitId, array $tanks, mixed $now): void
    {
        $keep = [];

        foreach ($tanks as $order => $tank) {
            $keep[] = $this->put('fuel_tanks', $unitId, $tank['name'], $tank['from'], [
                'jenis' => $tank['jenis'],
                'bbm_type_id' => $tank['bbm'],
                'fuel_type' => $tank['fuel'],
                'capacity_liter' => $tank['liter'],
                'is_daily_tank' => $tank['daily'],
                'sort_order' => $order + 1,
            ], $now);
        }

        $this->deactivateOthers('fuel_tanks', $unitId, $keep, $now);
    }

    /**
     * @param  list<array{name: string, from: string|null, uom: string}>  $lubricants
     */
    private function lubricants(int $unitId, array $lubricants, mixed $now): void
    {
        $keep = [];

        foreach ($lubricants as $order => $lubricant) {
            $keep[] = $this->put('lubricant_types', $unitId, $lubricant['name'], $lubricant['from'], [
                'unit_of_measure' => $lubricant['uom'],
                'sort_order' => $order + 1,
            ], $now);
        }

        $this->deactivateOthers('lubricant_types', $unitId, $keep, $now);
    }

    /**
     * @param  list<array{name: string, from: string|null, kategori: string, machine: int|null}>  $meters
     */
    private function kwhMeters(int $unitId, array $meters, mixed $now): void
    {
        $keep = [];

        foreach ($meters as $order => $meter) {
            $keep[] = $this->put('kwh_meters', $unitId, $meter['name'], $meter['from'], [
                'kategori' => $meter['kategori'],
                'machine_id' => $meter['machine'],
                'sort_order' => $order + 1,
            ], $now);
        }

        $this->deactivateOthers('kwh_meters', $unitId, $keep, $now);
    }
};
