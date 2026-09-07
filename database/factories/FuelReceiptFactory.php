<?php

namespace Database\Factories;

use App\Enums\TankFuelType;
use App\Models\FuelReceipt;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelReceipt>
 */
class FuelReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'report_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'fuel_type' => fake()->randomElement(TankFuelType::cases()),
            'supplier' => fake()->company(),
            'do_number' => fake()->unique()->bothify('DO-#####'),
            'unloading_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'volume_liter' => fake()->randomFloat(2, 5000, 500000),
            'calorie_value' => fake()->randomFloat(2, 8000, 11000),
            'price_per_liter' => fake()->randomFloat(2, 8000, 15000),
            'transport_cost' => fake()->randomFloat(2, 0, 5000000),
            'surveyor_cost' => fake()->randomFloat(2, 0, 2000000),
            'keterangan' => null,
            'input_by' => null,
        ];
    }
}
