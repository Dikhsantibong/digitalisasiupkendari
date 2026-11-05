<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Operators no longer see the Master Data pages (Unit Pembangkit, Master
     * Mesin, Master Pegawai), not even read-only.
     */
    public function up(): void
    {
        $ids = Permission::query()->whereIn('name', array_map(fn (PermissionName $p): string => $p->value, PermissionName::masterDataView()))->pluck('id')->all();

        Role::query()->where('name', RoleName::Operator->value)->get()
            ->each(fn (Role $role) => $role->permissions()->detach($ids));
    }

    public function down(): void
    {
        $ids = Permission::query()->whereIn('name', array_map(fn (PermissionName $p): string => $p->value, PermissionName::masterDataView()))->pluck('id')->all();

        Role::query()->where('name', RoleName::Operator->value)->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }
};
