<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarCombustionPressure;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarCombustionPressurePdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Pengukuran Tekanan Pembakaran (per mesin & tanggal uji).
 */
class CombustionPressureController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'combustion-pressure';
    }

    protected function title(): string
    {
        return 'Formulir Pengukuran Tekanan Pembakaran';
    }

    protected function model(): string
    {
        return HarCombustionPressure::class;
    }

    protected function builder(): HarCombustionPressurePdfBuilder
    {
        return app(HarCombustionPressurePdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganCombustionPressure;
    }

    protected function filePrefix(): string
    {
        return 'Tekanan_Pembakaran';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('measurements'),
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
            'standard_allowed' => 'Sesuai petunjuk pabrik / buku manual',
        ];
    }
}
