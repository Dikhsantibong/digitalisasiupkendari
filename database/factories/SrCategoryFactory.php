<?php

namespace Database\Factories;

use App\Models\SrCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SrCategory>
 */
class SrCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('????'),
            'name' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
