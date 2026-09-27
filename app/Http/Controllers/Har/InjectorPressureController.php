<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarInjectorPressure;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarInjectorPressurePdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Pengukuran Tekanan Pengabutan Injektor (per mesin & tanggal uji).
 */
class InjectorPressureController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'injector-pressure';
    }

    protected function title(): string
    {
        return 'Formulir Pengukuran Tekanan Pengabutan Injektor';
    }

    protected function model(): string
    {
        return HarInjectorPressure::class;
    }

    protected function builder(): HarInjectorPressurePdfBuilder
    {
        return app(HarInjectorPressurePdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganInjectorPressure;
    }

    protected function filePrefix(): string
    {
        return 'Tekanan_Pengabutan_Injektor';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('measurements'),
            'standard_allowed' => ['nullable', 'string'],
            'visual_inspection' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine, ['model_type' => '8M 453 AK', 'installed_power' => '2544']),
            'cylinders_count' => 8,
            'standard_allowed' => '270 kg/cm²',
        ];
    }
}
