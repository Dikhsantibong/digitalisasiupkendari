<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => fake()->name(),
            'nip' => fake()->unique()->numerify('##########'),
            'position' => fake()->randomElement(['Operator', 'Site Leader', 'Teknisi', 'TL Operasi', 'TL Pemeliharaan']),
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
