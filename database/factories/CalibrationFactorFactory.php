<?php

namespace Database\Factories;

use App\Enums\CalibrationFactorType;
use App\Models\CalibrationFactor;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalibrationFactor>
 */
class CalibrationFactorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'engine_id' => null,
            'factor_type' => fake()->randomElement(CalibrationFactorType::cases()),
            'value' => fake()->randomFloat(6, 0.5, 2.5),
            'effective_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
