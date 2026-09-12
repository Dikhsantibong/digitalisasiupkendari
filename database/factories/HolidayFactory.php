<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('2026-01-01', '2026-12-31');

        return [
            'year' => (int) $date->format('Y'),
            'date' => $date->format('Y-m-d'),
            'day_name' => $date->format('l'),
            'description' => fake()->sentence(3),
            'is_national' => true,
        ];
    }
}
