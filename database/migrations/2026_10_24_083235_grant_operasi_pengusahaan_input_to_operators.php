<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The operators of every unit (containerized or not) fill the Pengusahaan
     * Operasi inputs: add operasi.pengusahaan.input and grant it to Operator
     * and Project Leader — without re-syncing the other permissions a Super
     * Admin may have changed in Role & Akses.
     */
    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => PermissionName::OperasiPengusahaanInput->value],
            [
                'group' => PermissionName::OperasiPengusahaanInput->group(),
                'display_name' => PermissionName::OperasiPengusahaanInput->label(),
            ],
        );

        Role::query()
            ->whereIn('name', [RoleName::Operator->value, RoleName::ProjectLeaderOperasi->value])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->getKey()]));
    }

    public function down(): void
    {
        Permission::query()->where('name', PermissionName::OperasiPengusahaanInput->value)->delete();
    }
};
