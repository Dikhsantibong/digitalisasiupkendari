<?php

namespace App\Http\Controllers\Logistik;

use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Http\Controllers\BaseInstruksiKerjaController;
use App\Models\LogistikInstruksiKerja;
use App\Models\User;
use App\Support\LogistikIkTemplates;

/**
 * Input Instruksi Kerja (IK) Logistik & Gudang: the unit's IK documents (penerimaan material, stok opname gudang …),
 * written on the visual A4 editor from a template and printed in the MKP
 * layout — the same input as the IK Pemeliharaan & Operasi. Made by the
 * Koordinator Logistik, approved by the TL Pemeliharaan (user, 2026-11-09).
 */
class InstruksiKerjaController extends BaseInstruksiKerjaController
{
    protected function modelClass(): string
    {
        return LogistikInstruksiKerja::class;
    }

    protected function page(): string
    {
        return 'logistik/input/instruksi-kerja/index';
    }

    protected function routePrefix(): string
    {
        return 'logistik.input.instruksi-kerja';
    }

    protected function moduleName(): string
    {
        return 'Logistik & Gudang';
    }

    protected function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::LogistikInputView) || $user->hasPermissionTo(PermissionName::LogistikLaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::LogistikInputWrite);
    }

    protected function templates(): array
    {
        return LogistikIkTemplates::all();
    }

    /**
     * @return array{0: string, 1: EmployeePosition}
     */
    protected function preparedBy(): array
    {
        return ['Koordinator Logistik', EmployeePosition::KoordinatorLogistik];
    }

    /**
     * @return array{0: string, 1: EmployeePosition}
     */
    protected function approvedBy(): array
    {
        return ['Team Leader Pemeliharaan', EmployeePosition::TeamLeaderPemeliharaan];
    }
}
