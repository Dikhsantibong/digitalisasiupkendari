<?php

namespace Database\Factories;

use App\Models\ApdCategory;
use App\Models\ApdItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApdItem>
 */
class ApdItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'apd_category_id' => ApdCategory::factory(),
            'name' => fake()->words(2, true),
            'location' => fake()->word(),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
