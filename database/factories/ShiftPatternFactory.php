<?php

namespace Database\Factories;

use App\Models\ShiftPattern;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftPattern>
 */
class ShiftPatternFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'regu' => fake()->randomElement(['A', 'B', 'C']),
            'sequence' => 'OFF,OFF,S,S,P,P,M,M',
            'cycle_days' => 8,
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
