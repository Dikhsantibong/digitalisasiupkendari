<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The Akun Unit role (one account per unit for every divisi, scoped to that
     * unit). Created with its default permissions without re-syncing the
     * other roles, so what Role & Akses changed stays.
     */
    public function up(): void
    {
        $role = Role::query()->updateOrCreate(
            ['name' => RoleName::AkunUnit->value],
            [
                'display_name' => RoleName::AkunUnit->label(),
                'scope' => RoleName::AkunUnit->scope(),
                'description' => RoleName::AkunUnit->description(),
                'is_system' => true,
            ],
        );

        $ids = collect(RoleName::AkunUnit->defaultPermissions())
            ->map(fn (PermissionName $permission): int => Permission::query()->firstOrCreate(
                ['name' => $permission->value],
                ['group' => $permission->group(), 'display_name' => $permission->label()],
            )->getKey())
            ->all();

        $role->permissions()->syncWithoutDetaching($ids);
    }

    public function down(): void
    {
        Role::query()->where('name', RoleName::AkunUnit->value)->delete();
    }
};
