<?php

namespace Database\Factories;

use App\Models\WorkGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkGroup>
 */
class WorkGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('?????'),
            'name' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
