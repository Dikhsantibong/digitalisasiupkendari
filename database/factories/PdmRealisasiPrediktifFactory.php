<?php

namespace Database\Factories;

use App\Models\PdmRealisasiPrediktif;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdmRealisasiPrediktif>
 */
class PdmRealisasiPrediktifFactory extends Factory
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
            'no_urut' => 1,
            'uraian' => fake()->randomElement(['VIBRASI', 'SISTEM DC']),
            'mesin' => 'ZHEJIANG #'.fake()->numberBetween(1, 4),
            'rencana' => [3, 17],
            'realisasi' => [3],
            'durasi' => 2,
            'sort_order' => 0,
        ];
    }
}
