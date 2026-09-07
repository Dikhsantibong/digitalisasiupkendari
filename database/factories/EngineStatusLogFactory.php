<?php

namespace Database\Factories;

use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EngineStatusLog>
 */
class EngineStatusLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = Unit::factory();
        $start = fake()->dateTimeBetween('-1 month', 'now');
        $stop = (clone $start)->modify('+'.fake()->numberBetween(1, 20).' hours');

        return [
            'unit_id' => $unit,
            'engine_id' => Machine::factory(),
            'report_date' => $start->format('Y-m-d'),
            'status_code_id' => UnitStatusCode::factory(),
            'operator_name' => fake()->name(),
            'dispatcher_name' => fake()->name(),
            'start_datetime' => $start,
            'stop_datetime' => $stop,
            'duration_minutes' => (int) round(($stop->getTimestamp() - $start->getTimestamp()) / 60),
            'keterangan' => null,
            'input_by' => null,
        ];
    }
}
