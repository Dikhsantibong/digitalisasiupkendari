<?php

namespace Database\Factories;

use App\Models\PdmKesiapanApd;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdmKesiapanApd>
 */
class PdmKesiapanApdFactory extends Factory
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
            'kelompok' => 'Alat Pelindung Diri (APD)',
            'no_urut' => 1,
            'inspeksi' => fake()->randomElement(['Helm', 'Safety shoes', 'Ear plug', 'Respirator']),
            'jumlah' => fake()->numberBetween(1, 20),
            'satuan' => 'Buah',
            'kelayakan_apd' => 'Layak',
            'sort_order' => 0,
        ];
    }
}
