<?php

namespace Database\Seeders;

use App\Enums\LubricantUnit;
use App\Enums\StatusCodeCategory;
use App\Enums\TankFuelType;
use App\Models\Feeder;
use App\Models\FuelTank;
use App\Models\LubricantType;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the operasi master data for PLTD Poasia. Every unit maintains its own
 * lists — this seeder is one unit's example, to be verified with the user.
 *
 * Values the user must confirm (tank capacities, the full status-code catalogue,
 * per-machine fuel type and lubricants) are intentionally left approximate or
 * empty rather than guessed; the source Excel did not model them.
 */
class OperasiPoasiaSeeder extends Seeder
{
    use WithoutModelEvents;

    private const UNIT_CODE = 'PLTD-POASIA';

    /**
     * @var list<string>
     */
    private const FEEDERS = [
        'Andonohu', 'Lapuko', 'Teluk', 'Express', 'Express 5', 'PPS',
        'Tie Line (F Express)', 'Boulevard', 'Nii Tanasa', 'DS Coupling',
        'Kubra', 'Gubernur', 'Andonohu Baru', 'Coupling Andonohu',
        'Arena Mitsubishi 5/6', 'Arena Cummins',
    ];

    /**
     * @var list<array{name: string, uom: LubricantUnit}>
     */
    private const LUBRICANTS = [
        ['name' => 'Shell Diala B', 'uom' => LubricantUnit::Liter],
        ['name' => 'Thermo XT 32', 'uom' => LubricantUnit::Liter],
        ['name' => 'Meditran SX CH-4', 'uom' => LubricantUnit::Drum],
        ['name' => 'Shell Argina S3', 'uom' => LubricantUnit::Drum],
        ['name' => 'Trafolube A', 'uom' => LubricantUnit::Liter],
        ['name' => 'Total Aurelia TI3030', 'uom' => LubricantUnit::Drum],
    ];

    /**
     * @var list<array{name: string, fuel_type: TankFuelType, is_daily: bool}>
     */
    private const TANKS = [
        ['name' => 'Tangki HSD 1500 Ton', 'fuel_type' => TankFuelType::Hsd, 'is_daily' => false],
        ['name' => 'Tangki HSD 90 Ton', 'fuel_type' => TankFuelType::Hsd, 'is_daily' => false],
        ['name' => 'Tangki Harian HSD 3.5 KL (A)', 'fuel_type' => TankFuelType::Hsd, 'is_daily' => true],
        ['name' => 'Tangki Harian HSD 3.5 KL (B)', 'fuel_type' => TankFuelType::Hsd, 'is_daily' => true],
        ['name' => 'Tangki MFO 1500 Ton (A)', 'fuel_type' => TankFuelType::Mfo, 'is_daily' => false],
        ['name' => 'Tangki MFO 1500 Ton (B)', 'fuel_type' => TankFuelType::Mfo, 'is_daily' => false],
        ['name' => 'Tangki MFO 1500 Ton (C)', 'fuel_type' => TankFuelType::Mfo, 'is_daily' => false],
        ['name' => 'Tangki MFO 10 KL', 'fuel_type' => TankFuelType::Mfo, 'is_daily' => false],
        ['name' => 'Tangki MFO 5 KL (A)', 'fuel_type' => TankFuelType::Mfo, 'is_daily' => false],
        ['name' => 'Tangki MFO 5 KL (B)', 'fuel_type' => TankFuelType::Mfo, 'is_daily' => false],
    ];

    /**
     * Placeholder global status codes; the user completes the full catalogue.
     *
     * @var list<array{code: string, label: string, category: StatusCodeCategory}>
     */
    private const STATUS_CODES = [
        ['code' => 'RSH', 'label' => 'Reserve Shutdown', 'category' => StatusCodeCategory::Standby],
        ['code' => 'FO', 'label' => 'Forced Outage', 'category' => StatusCodeCategory::Gangguan],
        ['code' => 'MOH', 'label' => 'Maintenance / Overhaul', 'category' => StatusCodeCategory::Har],
    ];

    public function run(): void
    {
        $unit = Unit::query()->where('code', self::UNIT_CODE)->first();

        if ($unit === null) {
            return;
        }

        foreach (self::FEEDERS as $index => $name) {
            Feeder::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'name' => $name],
                ['sort_order' => $index, 'is_active' => true],
            );
        }

        foreach (self::LUBRICANTS as $index => $lubricant) {
            LubricantType::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'name' => $lubricant['name']],
                ['unit_of_measure' => $lubricant['uom'], 'sort_order' => $index, 'is_active' => true],
            );
        }

        foreach (self::TANKS as $index => $tank) {
            FuelTank::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'name' => $tank['name']],
                [
                    'fuel_type' => $tank['fuel_type'],
                    'is_daily_tank' => $tank['is_daily'],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }

        foreach (self::STATUS_CODES as $status) {
            UnitStatusCode::query()->updateOrCreate(
                ['unit_id' => null, 'code' => $status['code']],
                ['label' => $status['label'], 'category' => $status['category'], 'is_active' => true],
            );
        }
    }
}
