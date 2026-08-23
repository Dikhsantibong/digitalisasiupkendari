<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::snake(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'display_name' => Str::headline($name),
            'scope' => RoleScope::Unit,
            'description' => fake()->optional()->sentence(),
            'is_system' => false,
        ];
    }

    /**
     * Build one of the system roles, matching how the seeder creates it.
     */
    public function named(RoleName $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $role->value,
            'display_name' => $role->label(),
            'scope' => $role->scope(),
            'description' => $role->description(),
            'is_system' => true,
        ]);
    }

    public function withScope(RoleScope $scope): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => $scope,
        ]);
    }
}
