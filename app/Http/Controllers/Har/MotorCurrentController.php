<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarMotorCurrent;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarMotorCurrentPdfBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Akses 2 — Pengusahaan: Data Pengukuran Arus Kerja Elektro Motor (per mesin & tanggal uji).
 */
class MotorCurrentController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'motor-current';
    }

    protected function title(): string
    {
        return 'Data Pengukuran Arus Kerja Elektro Motor';
    }

    protected function model(): string
    {
        return HarMotorCurrent::class;
    }

    protected function builder(): HarMotorCurrentPdfBuilder
    {
        return app(HarMotorCurrentPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganMotorCurrent;
    }

    protected function filePrefix(): string
    {
        return 'Arus_Kerja_Elektro_Motor';
    }

    protected function rules(): array
    {
        return [
            ...$this->machineRules(),
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        return [
            'test_date' => $testDate,
            ...$this->machineDefaults($machine),
            'items' => HarMotorCurrentPdfBuilder::DEFAULT_MOTOR_ITEMS,
        ];
    }

    protected function prepareForSave(array $data, Request $request, ?Model $existing): array
    {
        $data['items'] = array_values(array_map(
            fn (array $item, int $index): array => [...$item, 'no' => $index + 1],
            $data['items'],
            array_keys($data['items']),
        ));

        return $data;
    }
}
