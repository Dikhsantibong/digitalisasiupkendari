<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\User;
use Database\Seeders\JofAccountSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Administrasi & Keuangan (user, 2026-11-20): the keuangan.* permissions,
     * the JOF Administrasi Umum role (scope UL) with its account per UL, and
     * keuangan.view for the Manager UL & Admin UL — added without re-syncing
     * what Role & Akses changed.
     */
    public function up(): void
    {
        $ids = [];
        foreach ([PermissionName::KeuanganView, PermissionName::KeuanganWrite, PermissionName::KeuanganSupplierManage] as $name) {
            $ids[$name->value] = Permission::query()->updateOrCreate(
                ['name' => $name->value],
                ['group' => $name->group(), 'display_name' => $name->label()],
            )->getKey();
        }

        $jof = Role::query()->updateOrCreate(
            ['name' => RoleName::JofAdministrasiUmum->value],
            [
                'display_name' => RoleName::JofAdministrasiUmum->label(),
                'scope' => RoleName::JofAdministrasiUmum->scope(),
                'description' => RoleName::JofAdministrasiUmum->description(),
                'is_system' => true,
            ],
        );
        $jof->permissions()->syncWithoutDetaching(
            collect(RoleName::JofAdministrasiUmum->defaultPermissions())
                ->map(fn (PermissionName $name): int => Permission::query()->updateOrCreate(
                    ['name' => $name->value],
                    ['group' => $name->group(), 'display_name' => $name->label()],
                )->getKey())
                ->unique()->all(),
        );

        Role::query()->where('name', RoleName::SuperAdmin->value)->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching(array_values($ids)));
        Role::query()->whereIn('name', [RoleName::ManagerUl->value, RoleName::AdminUl->value])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$ids[PermissionName::KeuanganView->value]]));

        (new JofAccountSeeder)->run();
    }

    public function down(): void
    {
        $emails = ServiceUnit::query()->get()->map(fn (ServiceUnit $serviceUnit): string => JofAccountSeeder::emailFor($serviceUnit))->all();
        User::query()->whereIn('email', $emails)->get()->each(fn (User $user) => $user->delete());
        Role::query()->where('name', RoleName::JofAdministrasiUmum->value)->delete();
        Permission::query()->whereIn('name', ['keuangan.view', 'keuangan.write', 'keuangan.supplier.manage'])->delete();
    }
};
