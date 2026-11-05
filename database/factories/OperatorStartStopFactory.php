<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\OperatorStartStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorStartStop>
 */
class OperatorStartStopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $engine = Machine::factory();

        return [
            'engine_id' => $engine,
            'unit_id' => fn (array $attributes): int => Machine::query()->findOrFail($attributes['engine_id'])->unit_id,
            'tanggal' => fake()->dateTimeThisMonth()->format('Y-m-d'),
            'start_time' => '08:00',
            'cbg_on' => '08:05',
            'cbg_off' => '15:55',
            'stop_time' => '16:00',
            'penyebab_stop' => 'normal',
            'keterangan' => null,
        ];
    }

    public function gangguan(): static
    {
        return $this->state(['penyebab_stop' => 'gangguan', 'keterangan' => 'Trip']);
    }
}
