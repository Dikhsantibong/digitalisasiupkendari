<?php

namespace Database\Factories;

use App\Models\LogistikDocumentRecord;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogistikDocumentRecord>
 */
class LogistikDocumentRecordFactory extends Factory
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
            'document_number' => 'LAP-LOG',
            'format' => 'html',
            'content_html' => '<div class="logistik-section"><p>'.fake()->sentence().'</p></div>',
            'content_version' => 1,
        ];
    }
}
