<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Logsheet hours lock 60 minutes after their time (user, 2026-11-24); TL
     * Operasi, TL Ophar and Project Leader may open a locked hour to correct the
     * input — added without re-syncing what Role & Akses changed.
     */
    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => PermissionName::OperatorLogsheetCorrect->value],
            [
                'group' => PermissionName::OperatorLogsheetCorrect->group(),
                'display_name' => PermissionName::OperatorLogsheetCorrect->label(),
            ],
        );

        Role::query()
            ->whereIn('name', [
                RoleName::SuperAdmin->value,
                RoleName::TeamLeaderOperasi->value,
                RoleName::TeamLeaderOphar->value,
                RoleName::ProjectLeaderOperasi->value,
            ])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->getKey()]));
    }

    public function down(): void
    {
        Permission::query()->where('name', PermissionName::OperatorLogsheetCorrect->value)->delete();
    }
};
