<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarClearanceValve;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarClearanceValvePdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Pengukuran Clearance Valve (per mesin & tanggal uji).
 */
class ClearanceValveController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'clearance-valve';
    }

    protected function title(): string
    {
        return 'Formulir Pengukuran Clearance Valve';
    }

    protected function model(): string
    {
        return HarClearanceValve::class;
    }

    protected function builder(): HarClearanceValvePdfBuilder
    {
        return app(HarClearanceValvePdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganClearanceValve;
    }

    protected function filePrefix(): string
    {
        return 'Clearance_Valve';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('measurements'),
            'standard_ex' => ['required', 'string', 'max:50'],
            'standard_in' => ['required', 'string', 'max:50'],
            'standard_allowed' => ['nullable', 'string'],
            'visual_inspection' => ['nullable', 'string'],
            'cylinder_notes' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine, ['model_type' => '8M 453 C', 'installed_power' => '2800']),
            'cylinders_count' => 8,
            'standard_ex' => '0.60 mm',
            'standard_in' => '0.30 mm',
        ];
    }
}
