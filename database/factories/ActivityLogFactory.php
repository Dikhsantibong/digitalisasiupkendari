<?php

namespace Database\Factories;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event' => fake()->randomElement(ActivityEvent::cases()),
            'description' => fake()->sentence(),
            'subject_type' => null,
            'subject_id' => null,
            'service_unit_id' => null,
            'unit_id' => null,
            'properties' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit_id' => $unit->getKey(),
            'service_unit_id' => $unit->service_unit_id,
        ]);
    }

    public function event(ActivityEvent $event): static
    {
        return $this->state(fn (array $attributes): array => [
            'event' => $event,
        ]);
    }
}
