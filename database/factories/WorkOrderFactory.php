<?php

namespace Database\Factories;

use App\Enums\WorkOrderSource;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'wonum' => 'WO'.fake()->unique()->numberBetween(10000, 99999),
            'description' => fake()->sentence(),
            'source' => WorkOrderSource::Manual,
            'report_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'service_cost' => fake()->randomFloat(2, 0, 5_000_000),
            'material_cost' => fake()->randomFloat(2, 0, 5_000_000),
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
