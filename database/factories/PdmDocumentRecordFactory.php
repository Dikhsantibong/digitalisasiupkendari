<?php

namespace Database\Factories;

use App\Models\PdmDocumentRecord;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdmDocumentRecord>
 */
class PdmDocumentRecordFactory extends Factory
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
            'type' => 'bulanan',
            'month' => fake()->numberBetween(1, 12),
            'year' => 2026,
            'document_number' => 'LAP-PDM',
            'format' => 'html',
            'content_html' => '<div class="pdm-section"><p>'.fake()->sentence().'</p></div>',
            'content_version' => 1,
        ];
    }
}
