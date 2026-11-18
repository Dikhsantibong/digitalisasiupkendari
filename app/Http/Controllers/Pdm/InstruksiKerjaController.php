<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Http\Controllers\BaseInstruksiKerjaController;
use App\Models\PdmInstruksiKerja;
use App\Models\User;
use App\Support\PdmIkTemplates;

/**
 * Input Instruksi Kerja (IK) PdM: the unit's IK documents (pengukuran vibrasi, sampel pelumas …),
 * written on the visual A4 editor from a template and printed in the MKP
 * layout — the same input as the IK Pemeliharaan & Operasi. Made by the
 * Koordinator PDM, approved by the TL Pemeliharaan (user, 2026-11-09).
 */
class InstruksiKerjaController extends BaseInstruksiKerjaController
{
    protected function modelClass(): string
    {
        return PdmInstruksiKerja::class;
    }

    protected function page(): string
    {
        return 'pdm/input/instruksi-kerja/index';
    }

    protected function routePrefix(): string
    {
        return 'pdm.input.instruksi-kerja';
    }

    protected function moduleName(): string
    {
        return 'PdM';
    }

    protected function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::PdmInputView) || $user->hasPermissionTo(PermissionName::PdmLaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::PdmInputWrite);
    }

    protected function templates(): array
    {
        return PdmIkTemplates::all();
    }

    /**
     * @return array{0: string, 1: EmployeePosition}
     */
    protected function preparedBy(): array
    {
        return ['Koordinator PDM', EmployeePosition::KoordinatorPdm];
    }

    /**
     * @return array{0: string, 1: EmployeePosition}
     */
    protected function approvedBy(): array
    {
        return ['Team Leader Pemeliharaan', EmployeePosition::TeamLeaderPemeliharaan];
    }
}
