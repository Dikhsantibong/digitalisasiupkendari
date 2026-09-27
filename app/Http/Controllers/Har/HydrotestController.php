<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarHydrotest;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarHydrotestPdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Checklist Hydrotest (per mesin & tanggal uji).
 */
class HydrotestController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'hydrotest';
    }

    protected function title(): string
    {
        return 'Formulir Checklist Hydrotest';
    }

    protected function model(): string
    {
        return HarHydrotest::class;
    }

    protected function builder(): HarHydrotestPdfBuilder
    {
        return app(HarHydrotestPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganHydrotest;
    }

    protected function filePrefix(): string
    {
        return 'Hydrotest';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('checklist_items'),
            'standard_allowed' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine, ['model_type' => '8M 453 C', 'installed_power' => '2800']),
            'cylinders_count' => 8,
            'standard_allowed' => 'Tidak ada kebocoran',
        ];
    }
}
