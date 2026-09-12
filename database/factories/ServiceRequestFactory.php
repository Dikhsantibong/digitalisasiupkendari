<?php

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderSource;
use App\Models\ServiceRequest;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'sr_number' => 'SR'.fake()->unique()->numberBetween(10000, 99999),
            'description' => fake()->sentence(),
            'status' => ServiceRequestStatus::Open,
            'source' => WorkOrderSource::Manual,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
