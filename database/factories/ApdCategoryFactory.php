<?php

namespace Database\Factories;

use App\Models\ApdCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApdCategory>
 */
class ApdCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('APD??'),
            'name' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
