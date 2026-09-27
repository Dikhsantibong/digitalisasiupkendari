<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarCrankshaftDeflection;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarCrankshaftDeflectionPdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Pengukuran Defleksi Crankshaft (per mesin & tanggal uji).
 */
class CrankshaftDeflectionController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'crankshaft-deflection';
    }

    protected function title(): string
    {
        return 'Formulir Pengukuran Defleksi Crankshaft';
    }

    protected function model(): string
    {
        return HarCrankshaftDeflection::class;
    }

    protected function builder(): HarCrankshaftDeflectionPdfBuilder
    {
        return app(HarCrankshaftDeflectionPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganCrankshaftDeflection;
    }

    protected function filePrefix(): string
    {
        return 'Defleksi_Crankshaft';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('measurements'),
            'standard_min' => ['required', 'string', 'max:50'],
            'standard_max' => ['required', 'string', 'max:50'],
            'standard_allowed' => ['nullable', 'string'],
            'visual_inspection' => ['nullable', 'string'],
            'cylinder_notes' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine, ['model_type' => '8M 453 AK', 'installed_power' => '2544']),
            'cylinders_count' => 8,
            'standard_min' => '-0.06',
            'standard_max' => '+0.08',
            'standard_allowed' => 'Min : -0.06, Max : +0.08',
        ];
    }
}
