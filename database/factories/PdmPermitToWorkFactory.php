<?php

namespace Database\Factories;

use App\Models\PdmPermitToWork;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdmPermitToWork>
 */
class PdmPermitToWorkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => fake()->numberBetween(1, 12),
            'no_urut' => fake()->unique()->numberBetween(1, 30),
            'uraian' => 'Pekerjaan '.fake()->words(3, true),
            'tanggal' => fake()->dateTimeBetween('2026-01-01', '2026-12-28')->format('Y-m-d'),
            'status' => fake()->randomElement(['open', 'close']),
        ];
    }
}
