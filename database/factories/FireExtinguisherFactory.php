<?php

namespace Database\Factories;

use App\Models\FireExtinguisher;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FireExtinguisher>
 */
class FireExtinguisherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'rfid' => fake()->unique()->numerify('RFID#######'),
            'location' => fake()->word(),
            'merk' => fake()->randomElement(['Yamato', 'Chubb', 'Servvo']),
            'jenis' => fake()->randomElement(['Dry Chemical', 'CO2', 'Foam']),
            'berat_kg' => fake()->randomElement([3.5, 6, 9]),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
