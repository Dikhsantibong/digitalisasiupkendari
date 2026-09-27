<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarVibration;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarVibrationPdfBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Akses 2 — Pengusahaan: Formulir Pengukuran Tekanan Vibrasi (per mesin & tanggal uji).
 */
class VibrationController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'vibration';
    }

    protected function title(): string
    {
        return 'Formulir Pengukuran Tekanan Vibrasi';
    }

    protected function model(): string
    {
        return HarVibration::class;
    }

    protected function builder(): HarVibrationPdfBuilder
    {
        return app(HarVibrationPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganVibration;
    }

    protected function filePrefix(): string
    {
        return 'Tekanan_Vibrasi';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            'measurements' => ['required', 'array', 'min:1'],
            'measurements.*' => ['array'],
            'standard_text' => ['nullable', 'string'],
            'max_text' => ['nullable', 'string'],
            'conclusion_text' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        // The standard measuring points (A1 … ) with empty readings.
        $points = array_map(
            fn (array $point): array => [...array_map(fn (): string => '', $point), 'pos' => $point['pos'], 'point' => $point['point']],
            HarVibration::sampleScanMeasurements(),
        );

        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine, ['model_type' => '8M 453 C', 'installed_power' => '2800']),
            'measurements' => $points,
        ];
    }

    protected function prepareForSave(array $data, Request $request, ?Model $existing): array
    {
        $data['measurements'] = array_values(array_map(function (array $row, int $index): array {
            $vMax = (string) ($row['v_max'] ?? '');
            $vMin = (string) ($row['v_min'] ?? '');
            $hMax = (string) ($row['h_max'] ?? '');
            $hMin = (string) ($row['h_min'] ?? '');

            return [
                ...$row,
                'pos' => $index + 1,
                'v_avg' => $vMax !== '' && $vMin !== '' ? HarVibrationPdfBuilder::calculateAvg($vMax, $vMin) : '',
                'h_avg' => $hMax !== '' && $hMin !== '' ? HarVibrationPdfBuilder::calculateAvg($hMax, $hMin) : '',
            ];
        }, $data['measurements'], array_keys($data['measurements'])));

        return $data;
    }
}
