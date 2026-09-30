<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Http\Controllers\BaseInstruksiKerjaController;
use App\Models\OperasiInstruksiKerja;
use App\Models\User;
use App\Support\OperasiIkTemplates;

/**
 * Input Instruksi Kerja (IK) Operasi: the unit's IK documents (start / stop
 * mesin …), written from a template, freely adjusted and printed in the MKP
 * layout — the same input as the IK Pemeliharaan.
 */
class InstruksiKerjaController extends BaseInstruksiKerjaController
{
    protected function modelClass(): string
    {
        return OperasiInstruksiKerja::class;
    }

    protected function page(): string
    {
        return 'operasi/input/instruksi-kerja/index';
    }

    protected function routePrefix(): string
    {
        return 'operasi.input.instruksi-kerja';
    }

    protected function moduleName(): string
    {
        return 'Operasi';
    }

    protected function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperasiInputView) || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperasiInputWrite);
    }

    protected function templates(): array
    {
        return OperasiIkTemplates::all();
    }

    /**
     * @return array{0: string, 1: EmployeePosition}
     */
    protected function preparedBy(): array
    {
        return ['Koordinator Operasi', EmployeePosition::KoordinatorOperasi];
    }
}
