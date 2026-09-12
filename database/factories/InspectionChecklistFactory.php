<?php

namespace Database\Factories;

use App\Models\InspectionChecklist;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionChecklist>
 */
class InspectionChecklistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'form_code' => fake()->randomElement(['tempat-kerja', 'rambu-k3', 'fire-alarm']),
            'item_text' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
