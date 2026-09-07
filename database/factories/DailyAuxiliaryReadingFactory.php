<?php

namespace Database\Factories;

use App\Models\AuxiliarySource;
use App\Models\DailyAuxiliaryReading;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyAuxiliaryReading>
 */
class DailyAuxiliaryReadingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'auxiliary_source_id' => AuxiliarySource::factory(),
            'report_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'stand_kwh_akhir' => fake()->randomFloat(4, 100, 99999),
            'stand_bbm_akhir' => fake()->randomFloat(4, 100, 99999),
            'input_by' => null,
        ];
    }
}
