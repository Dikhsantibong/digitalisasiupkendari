<?php

namespace Database\Factories;

use App\Models\PdmFormDocument;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdmFormDocument>
 */
class PdmFormDocumentFactory extends Factory
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
            'form' => 'kontrol-material',
            'year' => 2026,
            'month' => fake()->numberBetween(1, 12),
            'subject' => '',
            'header' => ['unit_lokasi' => fake()->city()],
        ];
    }
}
