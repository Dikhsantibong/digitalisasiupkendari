<?php

namespace Database\Factories;

use App\Models\K3ActivityType;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3ActivityType>
 */
class K3ActivityTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => fn (): int => Unit::query()->latest('id')->value('id') ?? Unit::factory()->create()->getKey(),
            'code' => fake()->unique()->lexify('K3????'),
            'name' => fake()->words(3, true),
            'category' => null,
            'default_pic' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
