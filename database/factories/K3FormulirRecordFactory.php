<?php

namespace Database\Factories;

use App\Models\K3FormulirRecord;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3FormulirRecord>
 */
class K3FormulirRecordFactory extends Factory
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
            'form' => 'pemeliharaan-tps-lb3',
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'week' => 0,
            'data' => ['sections' => [], 'header' => []],
            'catatan' => null,
            'format' => 'form',
        ];
    }
}
