<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarBatteryVoltage;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarBatteryVoltagePdfBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Akses 2 — Pengusahaan: Formulir Pengukuran Tegangan Baterai (per mesin & tanggal uji).
 */
class BatteryVoltageController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'battery-voltage';
    }

    protected function title(): string
    {
        return 'Formulir Pengukuran Tegangan Baterai';
    }

    protected function model(): string
    {
        return HarBatteryVoltage::class;
    }

    protected function builder(): HarBatteryVoltagePdfBuilder
    {
        return app(HarBatteryVoltagePdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganBatteryVoltage;
    }

    protected function filePrefix(): string
    {
        return 'Tegangan_Baterai';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            'cells_24v' => ['required', 'array', 'min:1'],
            'cells_24v.*' => ['array'],
            'cells_110v' => ['required', 'array', 'min:1'],
            'cells_110v.*' => ['array'],
            'charging_conditions' => ['required', 'array'],
            'charging_conditions.*' => ['array'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine),
            'cells_24v' => HarBatteryVoltagePdfBuilder::defaultCells24v(),
            'cells_110v' => HarBatteryVoltagePdfBuilder::defaultCells110v(),
            'charging_conditions' => HarBatteryVoltagePdfBuilder::DEFAULT_CHARGING_CONDITIONS,
        ];
    }

    protected function prepareForSave(array $data, Request $request, ?Model $existing): array
    {
        $data['summary_24v'] = HarBatteryVoltagePdfBuilder::calculateSummary($data['cells_24v']);
        $data['summary_110v'] = HarBatteryVoltagePdfBuilder::calculateSummary($data['cells_110v']);

        return $data;
    }
}
