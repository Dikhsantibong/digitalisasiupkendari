<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Project Leader (user, 2026-11-18): one "Laporan" menu with every report
     * by module (laporan.hub) and the Laporan SLA gabungan of the accessible
     * units (sla.gabungan). Added to the Project Leader (and Super Admin)
     * without re-syncing what Role & Akses changed.
     */
    public function up(): void
    {
        foreach ([PermissionName::LaporanHub, PermissionName::SlaGabungan] as $name) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $name->value],
                ['group' => $name->group(), 'display_name' => $name->label()],
            );

            Role::query()
                ->whereIn('name', [RoleName::SuperAdmin->value, RoleName::ProjectLeaderOperasi->value])
                ->get()
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->getKey()]));
        }
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [PermissionName::LaporanHub->value, PermissionName::SlaGabungan->value])->delete();
    }
};
