<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Material & Peralatan in Input Pemeliharaan: Harmes / Harlist check each
     * item through har.lapangan.material_peralatan. Add it for them (and Super
     * Admin) without re-syncing what Role & Akses changed.
     */
    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['name' => PermissionName::HarLapanganMaterialPeralatan->value],
            [
                'group' => PermissionName::HarLapanganMaterialPeralatan->group(),
                'display_name' => PermissionName::HarLapanganMaterialPeralatan->label(),
            ],
        );

        Role::query()
            ->whereIn('name', [RoleName::SuperAdmin->value, RoleName::Harmes->value, RoleName::Harlist->value])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->getKey()]));
    }

    public function down(): void
    {
        Permission::query()->where('name', PermissionName::HarLapanganMaterialPeralatan->value)->delete();
    }
};
