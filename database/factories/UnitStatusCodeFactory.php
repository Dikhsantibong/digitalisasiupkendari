<?php

namespace Database\Factories;

use App\Enums\StatusCodeCategory;
use App\Models\UnitStatusCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitStatusCode>
 */
class UnitStatusCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => null,
            'code' => fake()->unique()->lexify('???'),
            'label' => fake()->words(2, true),
            'category' => fake()->randomElement(StatusCodeCategory::cases()),
            'is_active' => true,
        ];
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => null]);
    }
}
