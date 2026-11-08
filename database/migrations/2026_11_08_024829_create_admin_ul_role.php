<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The Admin UL role: the Super Admin raises an Akun Unit to see every unit
     * of its UL (scope service unit). Created with its default permissions
     * without re-syncing the other roles.
     */
    public function up(): void
    {
        $role = Role::query()->updateOrCreate(
            ['name' => RoleName::AdminUl->value],
            [
                'display_name' => RoleName::AdminUl->label(),
                'scope' => RoleName::AdminUl->scope(),
                'description' => RoleName::AdminUl->description(),
                'is_system' => true,
            ],
        );

        $ids = collect(RoleName::AdminUl->defaultPermissions())
            ->map(fn (PermissionName $permission): int => Permission::query()->firstOrCreate(
                ['name' => $permission->value],
                ['group' => $permission->group(), 'display_name' => $permission->label()],
            )->getKey())
            ->all();

        $role->permissions()->syncWithoutDetaching($ids);
    }

    public function down(): void
    {
        Role::query()->where('name', RoleName::AdminUl->value)->delete();
    }
};
