<?php

namespace Database\Factories;

use App\Models\Feeder;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feeder>
 */
class FeederFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => fake()->unique()->streetName(),
            'feeder_type' => fake()->randomElement(['outgoing', 'express', 'tie_line']),
            'sort_order' => fake()->numberBetween(0, 30),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
