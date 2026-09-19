<?php

namespace Database\Factories;

use App\Models\PdmSampleMonitoring;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdmSampleMonitoring>
 */
class PdmSampleMonitoringFactory extends Factory
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
            'lokasi' => fake()->city(),
            'pic_monitoring' => fake()->name(),
            'rekap_targets' => [],
        ];
    }
}
