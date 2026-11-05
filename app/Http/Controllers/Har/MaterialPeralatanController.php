<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Http\Controllers\BaseMaterialPeralatanController;
use App\Models\HarMaterialPeralatan;
use App\Models\User;

/**
 * Input Pemeliharaan "Laporan Material dan Peralatan": the Koordinator
 * Pemeliharaan (har.input.write) prepares the list & stock; Harmes / Harlist
 * (har.lapangan.material_peralatan) check each item.
 */
class MaterialPeralatanController extends BaseMaterialPeralatanController
{
    protected function modelClass(): string
    {
        return HarMaterialPeralatan::class;
    }

    protected function page(): string
    {
        return 'har/input/material-peralatan/index';
    }

    protected function moduleName(): string
    {
        return 'pemeliharaan';
    }

    protected function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganMaterialPeralatan)
            || $user->hasPermissionTo(PermissionName::HarLaporanView);
    }

    protected function canManage(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::HarInputWrite);
    }

    protected function canCheckField(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganMaterialPeralatan);
    }
}
