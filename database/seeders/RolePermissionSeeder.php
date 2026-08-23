<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Synchronises the permission catalogue and the system roles with the code.
 *
 * The seeder is idempotent and safe to re-run after new permissions are added
 * to {@see PermissionName}: existing rows are updated, new ones are created, and
 * permissions a role already lost by hand are not silently restored — only the
 * system roles' default mapping is re-applied.
 */
class RolePermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $permissions = $this->seedPermissions();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName->value],
                [
                    'display_name' => $roleName->label(),
                    'scope' => $roleName->scope(),
                    'description' => $roleName->description(),
                    'is_system' => true,
                ],
            );

            $permissionIds = collect($roleName->defaultPermissions())
                ->map(fn (PermissionName $permission): int => $permissions[$permission->value])
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }

    /**
     * @return array<string, int> permission name keyed to its identifier
     */
    private function seedPermissions(): array
    {
        $ids = [];

        foreach (PermissionName::cases() as $permission) {
            $model = Permission::query()->updateOrCreate(
                ['name' => $permission->value],
                [
                    'group' => $permission->group(),
                    'display_name' => $permission->label(),
                ],
            );

            $ids[$permission->value] = $model->getKey();
        }

        return $ids;
    }
}
