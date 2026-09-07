<?php

namespace Database\Factories;

use App\Enums\StockItemType;
use App\Models\PhysicalStockTake;
use App\Models\ReportPeriod;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhysicalStockTake>
 */
class PhysicalStockTakeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'report_period_id' => ReportPeriod::factory(),
            'item_type' => StockItemType::Fuel,
            'tank_id' => null,
            'lubricant_type_id' => null,
            'physical_qty_liter' => fake()->randomFloat(2, 1000, 500000),
            'physical_drum' => null,
            'physical_cm' => null,
            'input_by' => null,
        ];
    }
}
