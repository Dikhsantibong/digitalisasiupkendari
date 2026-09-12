<?php

namespace Database\Factories;

use App\Models\BbmType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BbmType>
 */
class BbmTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('BBM-##'),
            'name' => fake()->word(),
            'category' => fake()->randomElement(['HSD', 'MFO', 'Biodiesel']),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
