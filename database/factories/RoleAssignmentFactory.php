<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleAssignment>
 */
class RoleAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'service_unit_id' => null,
            'unit_id' => null,
            'assigned_by' => null,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit_id' => $unit->getKey(),
            'service_unit_id' => null,
        ]);
    }

    public function forServiceUnit(ServiceUnit $serviceUnit): static
    {
        return $this->state(fn (array $attributes): array => [
            'service_unit_id' => $serviceUnit->getKey(),
            'unit_id' => null,
        ]);
    }
}
