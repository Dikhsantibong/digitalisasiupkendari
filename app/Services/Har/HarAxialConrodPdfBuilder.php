<?php

namespace App\Services\Har;

use App\Models\Employee;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class HarAxialConrodPdfBuilder
{
    /**
     * Build view data for the Axial Conrod & Baut Conrod PDF and HTML document.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildData(Unit $unit, ?Machine $machine = null, array $input = []): array
    {
        $testDate = ! empty($input['test_date']) ? Carbon::parse($input['test_date']) : Carbon::today();
        $cylindersCount = (int) ($input['cylinders_count'] ?? 8);
        $torqueStandard = ! empty($input['torque_standard']) ? (string) $input['torque_standard'] : '750 NM';

        // Pre-fill or use provided measurements
        $measurements = $input['measurements'] ?? [];
        if (empty($measurements) || count($measurements) !== $cylindersCount) {
            $existingMap = collect($measurements)->keyBy('cylinder')->all();
            $measurements = [];
            for ($i = 1; $i <= $cylindersCount; $i++) {
                $measurements[] = [
                    'cylinder' => $i,
                    'axial_check' => $existingMap[$i]['axial_check'] ?? 'baik',
                    'bolt_tightening' => $existingMap[$i]['bolt_tightening'] ?? 'baik',
                    'notes' => $existingMap[$i]['notes'] ?? '',
                ];
            }
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

        // Resolve names
        $managerName = $input['manager_ul_name'] ?? ($managerUl?->name ?? 'SURYADI PRATAMA');
        $tlName = $input['tl_har_name'] ?? ($tlHar?->name ?? 'SURYADI PRATAMA');
        $staffName = $input['staff_har_name'] ?? ($staffHar?->name ?? 'RAHMAT RAHIM FAISAL');

        // Resolve signatures with fallback to scanned official signatures
        $managerSignature = $this->resolveSignatureBase64($managerUl);
        if (! $managerSignature) {
            $managerSignature = $this->fileToBase64(public_path('images/har/signatures/suryadi_stamp.png'));
        }

        $tlSignature = $this->resolveSignatureBase64($tlHar);
        if (! $tlSignature) {
            $tlSignature = $this->fileToBase64(public_path('images/har/signatures/suryadi_sig.png'));
        }

        $staffSignature = $this->resolveSignatureBase64($staffHar);
        if (! $staffSignature) {
            $staffSignature = $this->fileToBase64(public_path('images/har/signatures/rahmat_sig.png'));
        }

        return [
            'unit' => $unit,
            'machine' => $machine,
            'ul_label' => $ulLabel,
            'document_number' => $input['document_number'] ?? 'FMKD-314-10.3.3.a-B7',
            'revision' => $input['revision'] ?? '03',
            'effective_date' => $input['effective_date'] ?? '31 Juli 2024',
            'test_date' => $testDate->locale('id')->translatedFormat('d F Y'),
            'test_date_raw' => $testDate->format('Y-m-d'),

            // Technical specs
            'brand' => $input['brand'] ?? ($machine?->brand ?? 'MAK'),
            'model_type' => $input['model_type'] ?? ($machine?->type ?? '8M 453 AK'),
            'serial_number' => $input['serial_number'] ?? ($machine?->serial_number ?? ''),
            'machine_number' => $input['machine_number'] ?? ($machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #'], '', $machine->name) : '3'),
            'installed_power' => $input['installed_power'] ?? ($machine?->capacity_kw ?? '2544'),
            'capable_power' => $input['capable_power'] ?? '',
            'rpm' => $input['rpm'] ?? '600',

            // Inspection data
            'cylinders_count' => $cylindersCount,
            'torque_standard' => $torqueStandard,
            'measurements' => $measurements,
            'standard_allowed' => $input['standard_allowed'] ?? '',
            'notes' => $input['notes'] ?? '',

            // Signatories
            'manager_ul_id' => $managerUl?->id,
            'manager_ul_name' => $managerName,
            'manager_ul_title' => $input['manager_ul_title'] ?? ('PH. Manager Unit PLTD '.($unit->serviceUnit?->name ?? $unit->name)),
            'manager_ul_signature' => $managerSignature,

            'tl_har_id' => $tlHar?->id,
            'tl_har_name' => $tlName,
            'tl_har_title' => $input['tl_har_title'] ?? 'Team Leader Pemeliharaan',
            'tl_har_signature' => $tlSignature,

            'staff_har_id' => $staffHar?->id,
            'staff_har_name' => $staffName,
            'staff_har_title' => $input['staff_har_title'] ?? 'Staf Pemeliharaan',
            'staff_har_signature' => $staffSignature,

            // Page settings
            'page_margin_top' => (int) ($input['page_margin_top'] ?? 10),
            'page_margin_bottom' => (int) ($input['page_margin_bottom'] ?? 10),
            'page_margin_left' => (int) ($input['page_margin_left'] ?? 12),
            'page_margin_right' => (int) ($input['page_margin_right'] ?? 12),
            'line_spacing' => (string) ($input['line_spacing'] ?? '1.15'),

            // Logos Base64
            'logo_pln' => $this->fileToBase64(public_path('logo/sidebar-logo.png')),
            'logo_k3' => $this->fileToBase64(public_path('logo/k3.png')),
        ];
    }

    /**
     * Render the PDF / HTML view.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderHtml(array $data): string
    {
        return view('har.formulir.axial-conrod-pdf', ['data' => $data])->render();
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
