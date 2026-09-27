<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarTimingInjectionPump;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarTimingInjectionPumpPdfBuilder;

/**
 * Akses 2 — Pengusahaan: Formulir Checklist Timing Injection Pump (per mesin & tanggal uji).
 */
class TimingInjectionPumpController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'timing-injection-pump';
    }

    protected function title(): string
    {
        return 'Formulir Checklist Timing Injection Pump';
    }

    protected function model(): string
    {
        return HarTimingInjectionPump::class;
    }

    protected function builder(): HarTimingInjectionPumpPdfBuilder
    {
        return app(HarTimingInjectionPumpPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganTimingInjectionPump;
    }

    protected function filePrefix(): string
    {
        return 'Timing_Injection_Pump';
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
            'standard_allowed' => 'Sesuai petunjuk pabrik / buku manual',
        ];
    }
}
