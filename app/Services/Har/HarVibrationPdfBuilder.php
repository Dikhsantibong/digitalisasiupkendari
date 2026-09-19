<?php

namespace App\Services\Har;

use App\Models\Employee;
use App\Models\HarVibration;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class HarVibrationPdfBuilder
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

        // Measurements resolution
        $rawMeasurements = $input['measurements'] ?? [];
        if (empty($rawMeasurements)) {
            $rawMeasurements = HarVibration::sampleScanMeasurements();
        }

        // Process measurements to ensure Avg is accurately formatted
        $measurements = [];
        foreach ($rawMeasurements as $item) {
            $vMax = $item['v_max'] ?? '';
            $vMin = $item['v_min'] ?? '';
            $vAvg = $item['v_avg'] ?? '';
            if ($vAvg === '' && $vMax !== '' && $vMin !== '') {
                $vAvg = self::calculateAvg($vMax, $vMin);
            }

            $hMax = $item['h_max'] ?? '';
            $hMin = $item['h_min'] ?? '';
            $hAvg = $item['h_avg'] ?? '';
            if ($hAvg === '' && $hMax !== '' && $hMin !== '') {
                $hAvg = self::calculateAvg($hMax, $hMin);
            }

            $measurements[] = [
                'pos' => $item['pos'] ?? count($measurements) + 1,
                'point' => $item['point'] ?? '',
                'v_max' => $vMax,
                'v_min' => $vMin,
                'v_avg' => $vAvg,
                'h_max' => $hMax,
                'h_min' => $hMin,
                'h_avg' => $hAvg,
                'notes' => $item['notes'] ?? '',
            ];
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

        return [
            'unit' => $unit,
            'machine' => $machine,
            'ul_label' => $ulLabel,
            'document_number' => $input['document_number'] ?? 'FMKD-314-10.3.3.a-B10',
            'revision' => $input['revision'] ?? '03',
            'effective_date' => $input['effective_date'] ?? '31 Juli 2024',
            'test_date' => $testDate->translatedFormat('d F Y'),
            'test_date_raw' => $testDate->format('Y-m-d'),

            // Technical specs
            'brand' => $input['brand'] ?? ($machine?->brand ?? 'MAK'),
            'model_type' => $input['model_type'] ?? ($machine?->type ?? '8M 453 C'),
            'installed_power' => $input['installed_power'] ?? ($machine?->capacity_kw ?? '2800'),
            'capable_power' => $input['capable_power'] ?? '1500 kW',
            'serial_number' => $input['serial_number'] ?? ($machine?->serial_number ?? ''),
            'machine_number' => $input['machine_number'] ?? ($machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #', '#'], '', $machine->name) : '4'),
            'rpm' => $input['rpm'] ?? '600',

            // Measurements
            'measurements' => $measurements,

            // Summary notes
            'standard_text' => $input['standard_text'] ?? '',
            'max_text' => $input['max_text'] ?? '',
            'conclusion_text' => $input['conclusion_text'] ?? '',

            // Signatories
            'manager_ul_id' => $managerUl?->id,
            'manager_ul_name' => $input['manager_ul_name'] ?? ($managerUl?->name ?? 'SURYADI PRATAMA'),
            'manager_ul_title' => $input['manager_ul_title'] ?? ('PH. Manager Unit '.$ulLabel),
            'manager_ul_signature' => $this->resolveSignatureBase64($managerUl),

            'tl_har_id' => $tlHar?->id,
            'tl_har_name' => $input['tl_har_name'] ?? ($tlHar?->name ?? 'SURYADI PRATAMA'),
            'tl_har_title' => $input['tl_har_title'] ?? ('Team Leader Pemeliharaan '.$ulLabel),
            'tl_har_signature' => $this->resolveSignatureBase64($tlHar),

            'staff_har_id' => $staffHar?->id,
            'staff_har_name' => $input['staff_har_name'] ?? ($staffHar?->name ?? 'RAHMAT RAHIM FAISAL'),
            'staff_har_title' => $input['staff_har_title'] ?? 'Staff Pemeliharaan',
            'staff_har_signature' => $this->resolveSignatureBase64($staffHar),

            // Page settings
            'page_margin_top' => (int) ($input['page_margin_top'] ?? 8),
            'page_margin_bottom' => (int) ($input['page_margin_bottom'] ?? 8),
            'page_margin_left' => (int) ($input['page_margin_left'] ?? 10),
            'page_margin_right' => (int) ($input['page_margin_right'] ?? 10),
            'line_spacing' => (string) ($input['line_spacing'] ?? '1.1'),

            // Logos & Diagram Base64
            'logo_pln' => $this->fileToBase64(public_path('logo/sidebar-logo.png')),
            'logo_k3' => $this->fileToBase64(public_path('logo/k3.png')),
            'diagram_image' => $this->fileToBase64(public_path('images/har/vibration-diagram.png')),
        ];
    }

    /**
     * Calculate average of max and min.
     */
    public static function calculateAvg(?string $max, ?string $min): string
    {
        if ($max === null || $min === null || trim($max) === '' || trim($min) === '' || $max === '-' || $min === '-') {
            return '';
        }
        $maxClean = (float) str_replace(',', '.', trim($max));
        $minClean = (float) str_replace(',', '.', trim($min));
        $avg = ($maxClean + $minClean) / 2;

        return number_format($avg, 2, ',', '');
    }

    /**
     * Render the PDF / HTML view.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderHtml(array $data): string
    {
        return view('har.formulir.vibration-pdf', ['data' => $data])->render();
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
