<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarAxialConrod;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarAxialConrodPdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Pemeriksaan Axial Conrod & Baut Conrod (per mesin & tanggal uji).
 */
class AxialConrodController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'axial-conrod';
    }

    protected function title(): string
    {
        return 'Formulir Pemeriksaan Axial Conrod & Baut Conrod';
    }

    protected function model(): string
    {
        return HarAxialConrod::class;
    }

    protected function builder(): HarAxialConrodPdfBuilder
    {
        return app(HarAxialConrodPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganAxialConrod;
    }

    protected function filePrefix(): string
    {
        return 'Axial_Conrod';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('measurements'),
            'torque_standard' => ['required', 'string', 'max:50'],
            'standard_allowed' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine, ['model_type' => '8M 453 AK', 'installed_power' => '2544']),
            'cylinders_count' => 8,
            'torque_standard' => '750 NM',
        ];
    }
}
