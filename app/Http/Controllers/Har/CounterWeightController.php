<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarCounterWeight;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarCounterWeightPdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Pemeriksaan Kekencangan Baut Counter Weight (per mesin & tanggal uji).
 */
class CounterWeightController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'counter-weight';
    }

    protected function title(): string
    {
        return 'Formulir Pemeriksaan Kekencangan Baut Counter Weight';
    }

    protected function model(): string
    {
        return HarCounterWeight::class;
    }

    protected function builder(): HarCounterWeightPdfBuilder
    {
        return app(HarCounterWeightPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganCounterWeight;
    }

    protected function filePrefix(): string
    {
        return 'Baut_Counter_Weight';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            ...$this->cylinderRules('measurements'),
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
            'standard_allowed' => 'Torsi Pengencangan Sesuai Manual Book / Kondisi Baik',
        ];
    }
}
