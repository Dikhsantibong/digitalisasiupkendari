<?php

namespace App\Http\Controllers\Har;

use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Http\Controllers\BaseInstruksiKerjaController;
use App\Models\HarInstruksiKerja;
use App\Models\User;
use App\Support\HarIkTemplates;

/**
 * Input Instruksi Kerja (IK) Pemeliharaan (Akses 1): the unit's IK documents,
 * written from a template, freely adjusted and printed in the MKP layout.
 */
class InstruksiKerjaController extends BaseInstruksiKerjaController
{
    protected function modelClass(): string
    {
        return HarInstruksiKerja::class;
    }

    protected function page(): string
    {
        return 'har/input/instruksi-kerja/index';
    }

    protected function routePrefix(): string
    {
        return 'har.input.instruksi-kerja';
    }

    protected function moduleName(): string
    {
        return 'Pemeliharaan';
    }

    protected function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::HarInputView) || $user->hasPermissionTo(PermissionName::HarLaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::HarInputWrite);
    }

    protected function templates(): array
    {
        return HarIkTemplates::all();
    }

    /**
     * @return array{0: string, 1: EmployeePosition}
     */
    protected function preparedBy(): array
    {
        return ['Koordinator HAR', EmployeePosition::KoordinatorPemeliharaan];
    }
}
