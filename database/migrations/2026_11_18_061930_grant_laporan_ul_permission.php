<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Laporan UL (user, 2026-11-18): Pengusahaan Pembangkit, Logistik, K3L and
     * the PPT bulanan of the service unit, for the Admin UL (and Super Admin)
     * only — added without re-syncing what Role & Akses changed.
     */
    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => PermissionName::LaporanUlView->value],
            [
                'group' => PermissionName::LaporanUlView->group(),
                'display_name' => PermissionName::LaporanUlView->label(),
            ],
        );

        Role::query()
            ->whereIn('name', [RoleName::SuperAdmin->value, RoleName::AdminUl->value])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->getKey()]));
    }

    public function down(): void
    {
        Permission::query()->where('name', PermissionName::LaporanUlView->value)->delete();
    }
};
