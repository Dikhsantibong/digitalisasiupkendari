<?php

namespace Database\Factories;

use App\Enums\PermissionGroup;
use App\Enums\PermissionName;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().'.'.fake()->word();

        return [
            'name' => $name,
            'group' => PermissionGroup::System,
            'display_name' => $name,
            'description' => null,
        ];
    }

    /**
     * Build one of the catalogued permissions.
     */
    public function named(PermissionName $permission): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $permission->value,
            'group' => $permission->group(),
            'display_name' => $permission->label(),
        ]);
    }
}
