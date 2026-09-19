<?php

namespace Database\Factories;

use App\Models\LogistikRekomendasi;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogistikRekomendasi>
 */
class LogistikRekomendasiFactory extends Factory
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
            'uraian' => fake()->randomElement(['Ketersediaan stok material', 'Material Consumable', 'Inventaris Tools']),
            'kondisi_existing' => fake()->sentence(),
            'tindak_lanjut' => fake()->sentence(),
            'keterangan' => fake()->words(3, true),
        ];
    }
}
