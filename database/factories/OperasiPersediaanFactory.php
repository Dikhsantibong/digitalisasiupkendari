<?php

namespace Database\Factories;

use App\Enums\PersediaanJenis;
use App\Models\OperasiPersediaan;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperasiPersediaan>
 */
class OperasiPersediaanFactory extends Factory
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
            'jenis' => PersediaanJenis::Bbm,
            'month' => fake()->numberBetween(1, 12),
            'year' => 2026,
            'opening' => [],
            'entries' => [],
            'closing' => [],
            'catatan' => null,
        ];
    }
}
