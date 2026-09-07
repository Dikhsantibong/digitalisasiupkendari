<?php

namespace Database\Factories;

use App\Models\DailyFeederReading;
use App\Models\Feeder;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyFeederReading>
 */
class DailyFeederReadingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'feeder_id' => Feeder::factory(),
            'report_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'stand_akhir' => fake()->randomFloat(4, 1000, 999999),
            'is_active_today' => true,
            'input_by' => null,
        ];
    }
}
