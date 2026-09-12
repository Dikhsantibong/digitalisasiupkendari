<?php

namespace Database\Seeders;

use App\Enums\LubricantUnit;
use App\Enums\StatusCodeCategory;
use App\Models\BbmType;
use App\Models\LubricantType;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Shared operasi master data: fuel (BBM) types & global status codes, plus a
 * common lubricant catalogue applied to every unit. Idempotent (keyed by
 * code/name). Categories/labels for status codes and lubricant units are
 * best-effort and can be corrected via the master CRUD.
 */
class OperasiMasterSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{code: string, name: string, category: string}>
     */
    private const BBM_TYPES = [
        ['code' => 'HSD', 'name' => 'High Speed Diesel', 'category' => 'HSD'],
        ['code' => 'B30', 'name' => 'Biosolar B30', 'category' => 'Biodiesel'],
        ['code' => 'B40', 'name' => 'Biosolar B40', 'category' => 'Biodiesel'],
        ['code' => 'MFO', 'name' => 'Marine Fuel Oil', 'category' => 'MFO'],
    ];

    /**
     * @var list<array{name: string, uom: LubricantUnit}>
     */
    private const LUBRICANTS = [
        ['name' => 'Meditran SX 15W/40 CH-4', 'uom' => LubricantUnit::Drum],
        ['name' => 'Salyx 420', 'uom' => LubricantUnit::Liter],
        ['name' => 'Salyx 430', 'uom' => LubricantUnit::Liter],
        ['name' => 'TravoLube A', 'uom' => LubricantUnit::Liter],
        ['name' => 'Turbolube 46', 'uom' => LubricantUnit::Liter],
        ['name' => 'Turbolube 68', 'uom' => LubricantUnit::Liter],
        ['name' => 'Shell Argina S3', 'uom' => LubricantUnit::Drum],
    ];

    /**
     * @var list<array{code: string, label: string, category: StatusCodeCategory}>
     */
    private const STATUS_CODES = [
        ['code' => 'FO', 'label' => 'Forced Outage', 'category' => StatusCodeCategory::Gangguan],
        ['code' => 'MO', 'label' => 'Maintenance Outage', 'category' => StatusCodeCategory::Har],
        ['code' => 'RSH', 'label' => 'Reserve Shutdown', 'category' => StatusCodeCategory::Standby],
        ['code' => 'P0', 'label' => 'Planned Outage', 'category' => StatusCodeCategory::Har],
        ['code' => 'MB', 'label' => 'Mampu Beban', 'category' => StatusCodeCategory::Operasi],
        ['code' => 'OPS', 'label' => 'Operasi', 'category' => StatusCodeCategory::Operasi],
    ];

    public function run(): void
    {
        foreach (self::BBM_TYPES as $index => $bbm) {
            BbmType::query()->updateOrCreate(
                ['code' => $bbm['code']],
                ['name' => $bbm['name'], 'category' => $bbm['category'], 'sort_order' => $index, 'is_active' => true],
            );
        }

        foreach (self::STATUS_CODES as $status) {
            UnitStatusCode::query()->updateOrCreate(
                ['unit_id' => null, 'code' => $status['code']],
                ['label' => $status['label'], 'category' => $status['category'], 'is_active' => true],
            );
        }

        // The lubricant catalogue is per unit; apply the shared list to each.
        foreach (Unit::query()->get() as $unit) {
            foreach (self::LUBRICANTS as $index => $lubricant) {
                LubricantType::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'name' => $lubricant['name']],
                    ['unit_of_measure' => $lubricant['uom'], 'sort_order' => $index, 'is_active' => true],
                );
            }
        }
    }
}
