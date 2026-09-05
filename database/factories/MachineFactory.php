<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => fake()->unique()->bothify('MESIN ##'),
            'type' => fake()->bothify('TYPE-###'),
            'serial_number' => fake()->unique()->numerify('SN########'),
            'capacity_kw' => fake()->randomFloat(2, 0.1, 20),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit_id' => $unit->getKey(),
        ]);
    }
}
