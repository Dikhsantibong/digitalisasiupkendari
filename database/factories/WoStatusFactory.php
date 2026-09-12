<?php

namespace Database\Factories;

use App\Models\WoStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WoStatus>
 */
class WoStatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('????'),
            'name' => fake()->word(),
            'is_closed' => false,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => ['is_closed' => true]);
    }
}
