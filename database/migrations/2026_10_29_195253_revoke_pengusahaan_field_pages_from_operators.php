<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The field permissions of the pages that moved to Pengusahaan Operasi
     * (Ikhtisar / Input Harian, Star-Stop, Feeder, Pasokan Cadangan,
     * Penerimaan BBM, Resource Pembangkit) are no longer in the Operator role's
     * defaults, but databases seeded before still give them to operators —
     * which opened Ikhtisar Sentral on their phones. Withdraw them.
     */
    private const MOVED = [
        PermissionName::OperasiLapanganDailyReport,
        PermissionName::OperasiLapanganStarStop,
        PermissionName::OperasiLapanganFeeder,
        PermissionName::OperasiLapanganAuxiliary,
        PermissionName::OperasiLapanganFuelReceipt,
        PermissionName::OperasiLapanganResourcePembangkit,
    ];

    public function up(): void
    {
        $ids = Permission::query()->whereIn('name', array_map(fn (PermissionName $p): string => $p->value, self::MOVED))->pluck('id')->all();

        Role::query()->where('name', RoleName::Operator->value)->get()
            ->each(fn (Role $role) => $role->permissions()->detach($ids));
    }

    public function down(): void
    {
        // Not restored: these pages belong to Pengusahaan Operasi.
    }
};
