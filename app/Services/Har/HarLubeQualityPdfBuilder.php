<?php

namespace App\Services\Har;

use App\Models\Employee;
use App\Models\HarLubeQuality;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class HarLubeQualityPdfBuilder
{
    /**
     * Build view data array for the PDF template.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildData(Unit $unit, ?Machine $machine = null, array $input = []): array
    {
        $testDate = ! empty($input['test_date']) ? Carbon::parse($input['test_date']) : Carbon::today();

        // Parameter measurements resolution
        $parameters = $input['parameters'] ?? [];
        if (empty($parameters)) {
            $parameters = HarLubeQuality::defaultParameters($testDate);
        }

        // Resolving service unit name for letterhead
        $serviceUnitName = $unit->serviceUnit?->name ?? $unit->name;
        $ulLabel = str_starts_with(strtoupper($serviceUnitName), 'UL')
            ? $serviceUnitName
            : 'UL '.$serviceUnitName;

        // Signatories resolution
        $managerUl = ! empty($input['manager_ul_id']) ? Employee::find($input['manager_ul_id']) : $unit->manager();
        $tlHar = ! empty($input['tl_har_id'])
            ? Employee::find($input['tl_har_id'])
            : $unit->employees()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('position', 'like', '%team leader pemeliharaan%')
                        ->orWhere('position', 'like', '%tl pemeliharaan%')
                        ->orWhere('position', 'like', '%pemeliharaan%');
                })->first();

        $staffHar = ! empty($input['staff_har_id'])
            ? Employee::find($input['staff_har_id'])
            : $unit->employees()
                ->where('is_active', true)
                ->where('id', '!=', $tlHar?->id)
                ->where(function ($q) {
                    $q->where('position', 'like', '%staf pemeliharaan%')
                        ->orWhere('position', 'like', '%staff%')
                        ->orWhere('position', 'like', '%teknisi%')
                        ->orWhere('position', 'like', '%operator%');
                })->first();

        // Photo resolution
        $photoBase64 = null;
        if (! empty($input['photo_path']) && $input['photo_path'] !== 'none') {
            $path = $input['photo_path'];
            if ($path === 'sample' || $path === '/images/har/sample-lube-photo.png') {
                $samplePath = public_path('images/har/sample-lube-photo.png');
                if (file_exists($samplePath)) {
                    $photoBase64 = $this->fileToBase64($samplePath);
                }
            } elseif (str_starts_with($path, 'data:image')) {
                $photoBase64 = $path;
            } elseif (Storage::disk('public')->exists($path)) {
                $photoBase64 = $this->fileToBase64(Storage::disk('public')->path($path));
            } elseif (is_file($path)) {
                $photoBase64 = $this->fileToBase64($path);
            }
        } elseif (! array_key_exists('photo_path', $input) || $input['photo_path'] === null) {
            // Default to sample scan photo if available and not explicitly removed
            $samplePath = public_path('images/har/sample-lube-photo.png');
            if (file_exists($samplePath)) {
                $photoBase64 = $this->fileToBase64($samplePath);
            }
        }

        // Machine number / name
        $machineName = $input['machine_name'] ?? ($machine?->name ?? '1');
        $cleanMachineNumber = $input['machine_number'] ?? ($machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #', '#'], '', $machine->name) : '1');

        return [
            'unit' => $unit,
            'machine' => $machine,
            'ul_label' => $ulLabel,
            'document_number' => $input['document_number'] ?? 'FMKD-305-14.3.2.b-A3',
            'revision' => $input['revision'] ?? '',
            'effective_date' => $input['effective_date'] ?? '31 - 07 - 2024',
            'page_number' => $input['page_number'] ?? '',
            'test_date' => $testDate->translatedFormat('d F Y'),
            'test_date_raw' => $testDate->format('Y-m-d'),

            // Metadata fields
            'unit_sentral' => $input['unit_sentral'] ?? ($unit->serviceUnit?->name ? 'ULPLTD '.strtoupper($unit->serviceUnit->name) : 'ULPLTD '.strtoupper($unit->name)),
            'machine_name' => $machineName,
            'machine_number' => $cleanMachineNumber,
            'serial_number' => $input['serial_number'] ?? ($machine?->serial_number ?? ''),
            'sample_point' => $input['sample_point'] ?? 'Sump Tank',

            // Measurements Table
            'parameters' => $parameters,

            // Sections
            'status_text' => $input['status_text'] ?? HarLubeQuality::defaultStatusText(),
            'standard_text' => $input['standard_text'] ?? HarLubeQuality::defaultStandardText(),
            'photo_base64' => $photoBase64,
            'photo_caption' => $input['photo_caption'] ?? HarLubeQuality::defaultPhotoCaption(),
            'analisa_text' => $input['analisa_text'] ?? HarLubeQuality::defaultAnalisaText(),
            'cba_text' => $input['cba_text'] ?? HarLubeQuality::defaultCbaText(),
            'rekomendasi_text' => $input['rekomendasi_text'] ?? HarLubeQuality::defaultRekomendasiText(),

            // Signatories
            'signature_location' => $input['signature_location'] ?? 'Kendari',
            'signature_date' => $input['signature_date'] ?? $testDate->translatedFormat('d F Y'),

            'manager_ul_id' => $managerUl?->id,
            'manager_ul_name' => $input['manager_ul_name'] ?? ($managerUl?->name ?? 'SURYADI PRATAMA'),
            'manager_ul_title' => $input['manager_ul_title'] ?? ('Plh. Manager Unit Layanan '.$ulLabel),
            'manager_ul_signature' => $this->resolveSignatureBase64($managerUl),

            'tl_har_id' => $tlHar?->id,
            'tl_har_name' => $input['tl_har_name'] ?? ($tlHar?->name ?? 'SURYADI PRATAMA'),
            'tl_har_title' => $input['tl_har_title'] ?? 'Team Leader Pemeliharaan',
            'tl_har_signature' => $this->resolveSignatureBase64($tlHar),

            'staff_har_id' => $staffHar?->id,
            'staff_har_name' => $input['staff_har_name'] ?? ($staffHar?->name ?? 'MUHAMMAD ABDUL LIIZAL'),
            'staff_har_title' => $input['staff_har_title'] ?? 'Staff Pemeliharaan',
            'staff_har_signature' => $this->resolveSignatureBase64($staffHar),

            // Page settings
            'page_margin_top' => (int) ($input['page_margin_top'] ?? 8),
            'page_margin_bottom' => (int) ($input['page_margin_bottom'] ?? 8),
            'page_margin_left' => (int) ($input['page_margin_left'] ?? 10),
            'page_margin_right' => (int) ($input['page_margin_right'] ?? 10),
            'line_spacing' => (string) ($input['line_spacing'] ?? '1.1'),

            // Logos Base64
            'logo_pln' => $this->fileToBase64(public_path('logo/sidebar-logo.png')),
        ];
    }

    /**
     * Render the PDF / HTML view.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderHtml(array $data): string
    {
        return view('har.formulir.lube-quality-pdf', ['data' => $data])->render();
    }

    private function resolveSignatureBase64(?Employee $employee): ?string
    {
        if (! $employee || empty($employee->signature_path)) {
            return null;
        }

        if (Storage::disk('public')->exists($employee->signature_path)) {
            $path = Storage::disk('public')->path($employee->signature_path);

            return $this->fileToBase64($path);
        }

        return null;
    }

    private function fileToBase64(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        return "data:{$mime};base64,".base64_encode($content);
    }
}
