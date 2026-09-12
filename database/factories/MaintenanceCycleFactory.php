<?php

namespace Database\Factories;

use App\Models\MaintenanceCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceCycle>
 */
class MaintenanceCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('P#'),
            'name' => fake()->words(2, true),
            'interval_days' => fake()->randomElement([7, 14, 84]),
            'description' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
