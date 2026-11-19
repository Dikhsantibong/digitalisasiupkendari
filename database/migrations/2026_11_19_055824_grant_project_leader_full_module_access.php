<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Project Leader (user, 2026-11-19): full Akses 1 of every module of its
     * unit — Operasi, Pemeliharaan, K3 & Keamanan, Logistik & Gudang, PdM &
     * Maturity Level (jadwal, input, formulir, master, laporan) — and shown as
     * "Project Leader" instead of "Project Leader Operasi". Permissions are
     * added without removing anything Role & Akses changed; the role code
     * (project_leader_operasi) stays.
     */
    public function up(): void
    {
        $role = Role::query()->where('name', RoleName::ProjectLeaderOperasi->value)->first();
        if ($role === null) {
            return;
        }

        $role->forceFill([
            'display_name' => RoleName::ProjectLeaderOperasi->label(),
            'description' => RoleName::ProjectLeaderOperasi->description(),
        ])->save();

        $ids = collect(RoleName::projectLeaderModulePermissions())
            ->map(fn (PermissionName $name): int => Permission::query()->updateOrCreate(
                ['name' => $name->value],
                ['group' => $name->group(), 'display_name' => $name->label()],
            )->getKey())
            ->unique()
            ->all();
        $role->permissions()->syncWithoutDetaching($ids);

        // The jabatan text of the accounts (users.position) follows the new name.
        DB::table('users')->where('position', 'Project Leader Operasi')->update(['position' => 'Project Leader']);
    }

    public function down(): void
    {
        Role::query()->where('name', RoleName::ProjectLeaderOperasi->value)->update(['display_name' => 'Project Leader Operasi']);
        DB::table('users')->where('position', 'Project Leader')->whereIn('id', function ($query): void {
            $query->select('role_assignments.user_id')->from('role_assignments')
                ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
                ->where('roles.name', RoleName::ProjectLeaderOperasi->value);
        })->update(['position' => 'Project Leader Operasi']);
    }
};
