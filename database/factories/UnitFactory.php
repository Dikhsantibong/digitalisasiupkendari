<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(UnitType::cases());
        $name = $type->label().' '.fake()->unique()->city();

        return [
            'service_unit_id' => null,
            'code' => Str::upper(Str::random(8)),
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => $type,
            'installed_capacity_mw' => fake()->randomFloat(2, 1, 60),
            'location' => fake()->city().', Sulawesi Tenggara',
            'status' => UnitStatus::Operating,
            'is_active' => true,
        ];
    }

    public function forServiceUnit(ServiceUnit $serviceUnit): static
    {
        return $this->state(fn (array $attributes): array => [
            'service_unit_id' => $serviceUnit->getKey(),
        ]);
    }

    public function ofType(UnitType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    public function withStatus(UnitStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}
