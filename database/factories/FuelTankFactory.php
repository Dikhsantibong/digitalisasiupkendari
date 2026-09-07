<?php

namespace Database\Factories;

use App\Enums\TankFuelType;
use App\Models\FuelTank;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelTank>
 */
class FuelTankFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'code' => fake()->unique()->bothify('TNK-##'),
            'name' => fake()->words(2, true),
            'fuel_type' => fake()->randomElement(TankFuelType::cases()),
            'capacity_liter' => fake()->randomFloat(2, 5000, 1500000),
            'is_daily_tank' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
