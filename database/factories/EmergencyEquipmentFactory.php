<?php

namespace Database\Factories;

use App\Models\EmergencyEquipment;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyEquipment>
 */
class EmergencyEquipmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'group_name' => fake()->randomElement(['Fire Protection', 'Emergency Response', 'Medical']),
            'name' => fake()->words(2, true),
            'location' => fake()->word(),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
