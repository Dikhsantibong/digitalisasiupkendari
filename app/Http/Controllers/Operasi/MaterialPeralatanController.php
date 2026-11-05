<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\PermissionName;
use App\Http\Controllers\BaseMaterialPeralatanController;
use App\Models\OperasiMaterialPeralatan;
use App\Models\User;

/**
 * Input Operasi "Laporan Material dan Peralatan": the Koordinator
 * (operasi.input.write) or the Project Leader (operasi.material_peralatan.kelola)
 * prepares the list & stock; the operators (operasi.lapangan.material_peralatan)
 * check each item.
 */
class MaterialPeralatanController extends BaseMaterialPeralatanController
{
    protected function modelClass(): string
    {
        return OperasiMaterialPeralatan::class;
    }

    protected function page(): string
    {
        return 'operasi/input/material-peralatan/index';
    }

    protected function moduleName(): string
    {
        return 'operasi';
    }

    protected function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganMaterialPeralatan)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    protected function canManage(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperasiInputWrite)
            || $user->hasPermissionTo(PermissionName::OperasiMaterialPeralatanKelola);
    }

    protected function canCheckField(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganMaterialPeralatan);
    }
}
