<?php

namespace Database\Factories;

use App\Models\LogistikJadwalRow;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogistikJadwalRow>
 */
class LogistikJadwalRowFactory extends Factory
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
            'jadwal' => 'meeting',
            'year' => 2026,
            'month' => fake()->numberBetween(1, 12),
            'nama' => 'OFFICER LOGISTIK & GUDANG',
            'days' => ['3' => 'R', '4' => 'D'],
            'sort_order' => 0,
        ];
    }
}
