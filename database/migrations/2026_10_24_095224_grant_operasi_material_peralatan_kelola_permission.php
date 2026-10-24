<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The Material & Peralatan list and its stock are prepared by the
     * Koordinator (operasi.input.write) or the Project Leader; operators only
     * check each item. Add operasi.material_peralatan.kelola for the Project
     * Leader (and Super Admin) without re-syncing what Role & Akses changed.
     */
    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => PermissionName::OperasiMaterialPeralatanKelola->value],
            [
                'group' => PermissionName::OperasiMaterialPeralatanKelola->group(),
                'display_name' => PermissionName::OperasiMaterialPeralatanKelola->label(),
            ],
        );

        Permission::query()
            ->where('name', PermissionName::OperasiLapanganMaterialPeralatan->value)
            ->update(['display_name' => PermissionName::OperasiLapanganMaterialPeralatan->label()]);

        Role::query()
            ->whereIn('name', [RoleName::SuperAdmin->value, RoleName::ProjectLeaderOperasi->value])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->getKey()]));
    }

    public function down(): void
    {
        Permission::query()->where('name', PermissionName::OperasiMaterialPeralatanKelola->value)->delete();
    }
};
