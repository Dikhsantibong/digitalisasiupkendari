<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The operators no longer fill the Pengusahaan Operasi inputs for now
     * (only the core Operator menus and Input Operasi): withdraw
     * operasi.pengusahaan.input from the Operator role. The Project Leader
     * keeps it; Role & Akses can grant it back.
     */
    public function up(): void
    {
        $permission = Permission::query()->where('name', PermissionName::OperasiPengusahaanInput->value)->first();
        if ($permission === null) {
            return;
        }

        Role::query()->where('name', RoleName::Operator->value)->get()
            ->each(fn (Role $role) => $role->permissions()->detach($permission->getKey()));
    }

    public function down(): void
    {
        $permission = Permission::query()->where('name', PermissionName::OperasiPengusahaanInput->value)->first();
        if ($permission === null) {
            return;
        }

        Role::query()->where('name', RoleName::Operator->value)->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->getKey()]));
    }
};
