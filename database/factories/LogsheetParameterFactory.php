<?php

namespace Database\Factories;

use App\Enums\PlantType;
use App\Models\LogsheetParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogsheetParameter>
 */
class LogsheetParameterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_type' => PlantType::All,
            'code' => fake()->unique()->lexify('PARAM????'),
            'name' => fake()->words(2, true),
            'unit_of_measure' => fake()->randomElement(['kW', '°C', 'bar', 'A', 'Hz']),
            'sub_channel' => null,
            'sort_order' => fake()->numberBetween(0, 30),
            'is_active' => true,
        ];
    }
}
