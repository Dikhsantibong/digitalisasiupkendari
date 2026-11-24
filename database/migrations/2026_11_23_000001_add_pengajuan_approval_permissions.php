<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Separate Pengajuan Absensi (izin / sakit / cuti / tukar shift) approval
     * permissions for Operasi (TL Operasi / TL Ophar / PL / Koordinator
     * Operasi) and HAR field staff (TL Pemeliharaan / TL Ophar).
     */
    public function up(): void
    {
        $approveOperasi = Permission::query()->updateOrCreate(
            ['name' => PermissionName::OperatorPengajuanApproveOperasi->value],
            [
                'group' => PermissionName::OperatorPengajuanApproveOperasi->group(),
                'display_name' => PermissionName::OperatorPengajuanApproveOperasi->label(),
            ],
        );

        $approveHar = Permission::query()->updateOrCreate(
            ['name' => PermissionName::OperatorPengajuanApproveHar->value],
            [
                'group' => PermissionName::OperatorPengajuanApproveHar->group(),
                'display_name' => PermissionName::OperatorPengajuanApproveHar->label(),
            ],
        );

        Role::query()
            ->where(function ($query): void {
                $query->whereIn('name', [
                    RoleName::SuperAdmin->value,
                    RoleName::TeamLeaderOperasi->value,
                    RoleName::TeamLeaderOphar->value,
                    RoleName::KoordinatorOperasi->value,
                    RoleName::ProjectLeaderOperasi->value,
                ])->orWhereHas('permissions', fn ($q) => $q->where('name', PermissionName::OperatorAbsensiWrite->value));
            })
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$approveOperasi->getKey()]));

        Role::query()
            ->whereIn('name', [
                RoleName::SuperAdmin->value,
                RoleName::TeamLeaderPemeliharaan->value,
                RoleName::TeamLeaderOphar->value,
            ])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$approveHar->getKey()]));
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            PermissionName::OperatorPengajuanApproveOperasi->value,
            PermissionName::OperatorPengajuanApproveHar->value,
        ])->delete();
    }
};
