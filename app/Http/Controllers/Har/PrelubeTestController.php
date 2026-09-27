<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarPrelubeTest;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarPrelubeTestPdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Checklist Prelube Test (per mesin & tanggal uji).
 */
class PrelubeTestController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'prelube-test';
    }

    protected function title(): string
    {
        return 'Formulir Checklist Prelube Test';
    }

    protected function model(): string
    {
        return HarPrelubeTest::class;
    }

    protected function builder(): HarPrelubeTestPdfBuilder
    {
        return app(HarPrelubeTestPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganPrelubeTest;
    }

    protected function filePrefix(): string
    {
        return 'Prelube_Test';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('checklist_items'),
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine, ['model_type' => '8M 453 AK', 'installed_power' => '2544']),
            'cylinders_count' => 8,
        ];
    }
}
