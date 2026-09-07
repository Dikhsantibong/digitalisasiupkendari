<?php

namespace Database\Factories;

use App\Models\LubricantReceipt;
use App\Models\LubricantType;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LubricantReceipt>
 */
class LubricantReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'report_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'lubricant_type_id' => LubricantType::factory(),
            'volume' => fake()->randomFloat(2, 10, 2000),
            'do_number' => fake()->unique()->bothify('DO-OLI-####'),
            'input_by' => null,
        ];
    }
}
