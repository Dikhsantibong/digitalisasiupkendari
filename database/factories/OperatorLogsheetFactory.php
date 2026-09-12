<?php

namespace Database\Factories;

use App\Enums\LogsheetStatus;
use App\Models\Machine;
use App\Models\OperatorLogsheet;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorLogsheet>
 */
class OperatorLogsheetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'engine_id' => Machine::factory(),
            'log_date' => '2026-08-10',
            'status' => LogsheetStatus::Draft,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LogsheetStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
