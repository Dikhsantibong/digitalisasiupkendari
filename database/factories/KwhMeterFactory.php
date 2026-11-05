<?php

namespace Database\Factories;

use App\Enums\KwhMeterKategori;
use App\Models\KwhMeter;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KwhMeter>
 */
class KwhMeterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => 'kWh '.fake()->words(2, true),
            'kategori' => fake()->randomElement(KwhMeterKategori::cases()),
            'machine_id' => null,
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
