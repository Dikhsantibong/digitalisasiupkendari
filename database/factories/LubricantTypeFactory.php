<?php

namespace Database\Factories;

use App\Enums\LubricantUnit;
use App\Models\LubricantType;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LubricantType>
 */
class LubricantTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'code' => fake()->unique()->bothify('OLI-##'),
            'name' => fake()->unique()->words(2, true),
            'unit_of_measure' => fake()->randomElement(LubricantUnit::cases()),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
