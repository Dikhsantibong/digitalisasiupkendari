<?php

namespace Database\Factories;

use App\Models\LogistikFormRow;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogistikFormRow>
 */
class LogistikFormRowFactory extends Factory
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
            'form' => 'pendukung',
            'year' => 2026,
            'month' => fake()->numberBetween(1, 12),
            'section' => null,
            'data' => ['uraian' => '1.1 DATA RESUME', 'folder' => 'Resume', 'detail' => '', 'keterangan' => ''],
            'sort_order' => 0,
        ];
    }
}
