<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Models\HarLubeQuality;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarLubeQualityPdfBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Akses 2 — Pengusahaan: Formulir Pengukuran Kualitas Pelumas (per mesin & tanggal uji).
 */
class LubeQualityController extends HarFormulirController
{
    protected function slug(): string
    {
        return 'lube-quality';
    }

    protected function title(): string
    {
        return 'Formulir Pengukuran Kualitas Pelumas';
    }

    protected function model(): string
    {
        return HarLubeQuality::class;
    }

    protected function builder(): HarLubeQualityPdfBuilder
    {
        return app(HarLubeQualityPdfBuilder::class);
    }

    protected function lapanganPermission(): PermissionName
    {
        return PermissionName::HarLapanganLubeQuality;
    }

    protected function filePrefix(): string
    {
        return 'Kualitas_Pelumas';
    }

    protected function rules(): array
    {
        return [
            'unit_sentral' => ['nullable', 'string', 'max:150'],
            'machine_name' => ['nullable', 'string', 'max:100'],
            'machine_number' => ['nullable', 'string', 'max:50'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'sample_point' => ['required', 'string', 'max:100'],
            'parameters' => ['required', 'array', 'min:1'],
            'parameters.*' => ['array'],
            'status_text' => ['nullable', 'string'],
            'standard_text' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            'photo_caption' => ['nullable', 'string'],
            'analisa_text' => ['nullable', 'string'],
            'cba_text' => ['nullable', 'string'],
            'rekomendasi_text' => ['nullable', 'string'],
        ];
    }

    protected function formValues(array $viewData): array
    {
        return array_diff_key(parent::formValues($viewData), array_flip(['photo', 'remove_photo']));
    }

    protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array
    {
        $date = Carbon::parse($testDate);
        $parameters = array_map(
            fn (array $row): array => [...array_map(fn (): string => '', $row), 'tanggal' => $row['tanggal']],
            HarLubeQuality::defaultParameters($date),
        );

        return [
            'test_date' => $testDate,
            'unit_sentral' => 'ULPLTD '.strtoupper($unit->serviceUnit?->name ?? $unit->name),
            'machine_name' => $machine?->name ?? '1',
            'machine_number' => $machine ? trim(str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #', '#'], '', $machine->name)) : '1',
            'serial_number' => $machine?->serial_number ?? '',
            'sample_point' => 'Sump Tank',
            'parameters' => $parameters,
            'status_text' => HarLubeQuality::defaultStatusText(),
            'standard_text' => HarLubeQuality::defaultStandardText(),
            'photo_path' => 'none',
            'photo_caption' => HarLubeQuality::defaultPhotoCaption(),
            'analisa_text' => '',
            'cba_text' => '',
            'rekomendasi_text' => '',
            'signature_location' => 'Kendari',
            'signature_date' => $date->translatedFormat('d F Y'),
        ];
    }

    protected function prepareForSave(array $data, Request $request, ?Model $existing): array
    {
        $photoPath = $existing?->getAttribute('photo_path') ?? 'none';

        if ($request->hasFile('photo')) {
            $this->deletePhoto($photoPath);
            $photoPath = $request->file('photo')->store('har-lube-quality', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($photoPath);
            $photoPath = 'none';
        }

        unset($data['photo'], $data['remove_photo']);

        return [
            ...$data,
            'photo_path' => $photoPath,
            'signature_location' => $existing?->getAttribute('signature_location') ?? 'Kendari',
            'signature_date' => Carbon::parse($request->input('test_date'))->translatedFormat('d F Y'),
        ];
    }

    protected function extraProps(?Model $record): array
    {
        $path = $record?->getAttribute('photo_path');

        return [
            'photo_url' => $path && $path !== 'none' && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null,
        ];
    }

    private function deletePhoto(?string $path): void
    {
        if ($path && $path !== 'none' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
