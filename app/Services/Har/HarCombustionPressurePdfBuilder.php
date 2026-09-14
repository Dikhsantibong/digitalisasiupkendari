<?php

namespace App\Services\Har;

use App\Models\Employee;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class HarCombustionPressurePdfBuilder
{
    /**
     * Build view data for the Combustion Pressure PDF and HTML document.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function buildData(Unit $unit, ?Machine $machine = null, array $input = []): array
    {
        $testDate = ! empty($input['test_date']) ? Carbon::parse($input['test_date']) : Carbon::today();
        $cylindersCount = (int) ($input['cylinders_count'] ?? 8);

        // Pre-fill or use provided measurements
        $measurements = $input['measurements'] ?? [];
        if (empty($measurements) || count($measurements) !== $cylindersCount) {
            $existingMap = collect($measurements)->keyBy('cylinder')->all();
            $measurements = [];
            for ($i = 1; $i <= $cylindersCount; $i++) {
                $measurements[] = [
                    'cylinder' => $i,
                    'combustion_pressure' => $existingMap[$i]['combustion_pressure'] ?? '',
                    'exhaust_temp' => $existingMap[$i]['exhaust_temp'] ?? '',
                    'rack_position' => $existingMap[$i]['rack_position'] ?? '',
                ];
            }
        }

        // Resolving service unit name for letterhead
        $serviceUnitName = $unit->serviceUnit?->name ?? $unit->name;
        $ulLabel = str_starts_with(strtoupper($serviceUnitName), 'UL')
            ? $serviceUnitName
            : 'UL ' . $serviceUnitName;

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
            'document_number' => $input['document_number'] ?? 'FMKD-314-10.3.3.a-B4',
            'revision' => $input['revision'] ?? '03',
            'effective_date' => $input['effective_date'] ?? '31 Juli 2024',
            'test_date' => $testDate->translatedFormat('d F Y'),
            'test_date_raw' => $testDate->format('Y-m-d'),

            // Technical specs
            'brand' => $input['brand'] ?? 'MAK',
            'model_type' => $input['model_type'] ?? ($machine?->type ?? '8M 453 AK'),
            'serial_number' => $input['serial_number'] ?? ($machine?->serial_number ?? ''),
            'machine_number' => $input['machine_number'] ?? ($machine ? str_replace('MIRRLEES #', '', $machine->name) : '1'),
            'installed_power' => $input['installed_power'] ?? ($machine?->capacity_kw ?? '2544'),
            'capable_power' => $input['capable_power'] ?? '1300',
            'rpm' => $input['rpm'] ?? '600',

            // Measurements & Notes
            'cylinders_count' => $cylindersCount,
            'measurements' => $measurements,
            'cylinder_notes' => $input['cylinder_notes'] ?? '',
            'standard_allowed' => $input['standard_allowed'] ?? 'Sesuai petunjuk pabrik / buku manual',
            'visual_inspection' => $input['visual_inspection'] ?? '',

            // Signatories
            'manager_ul_id' => $managerUl?->id,
            'manager_ul_name' => $input['manager_ul_name'] ?? ($managerUl?->name ?? 'Manager UL'),
            'manager_ul_title' => $input['manager_ul_title'] ?? ('Manager ' . $ulLabel),
            'manager_ul_signature' => $this->resolveSignatureBase64($managerUl),

            'tl_har_id' => $tlHar?->id,
            'tl_har_name' => $input['tl_har_name'] ?? ($tlHar?->name ?? 'Team Leader Pemeliharaan'),
            'tl_har_title' => $input['tl_har_title'] ?? 'Team Leader Pemeliharaan',
            'tl_har_signature' => $this->resolveSignatureBase64($tlHar),

            'staff_har_id' => $staffHar?->id,
            'staff_har_name' => $input['staff_har_name'] ?? ($staffHar?->name ?? 'Staff Pemeliharaan'),
            'staff_har_title' => $input['staff_har_title'] ?? 'Staf Pemeliharaan',
            'staff_har_signature' => $this->resolveSignatureBase64($staffHar),

            // Page settings
            'page_margin_top' => (int) ($input['page_margin_top'] ?? 12),
            'page_margin_bottom' => (int) ($input['page_margin_bottom'] ?? 12),
            'page_margin_left' => (int) ($input['page_margin_left'] ?? 15),
            'page_margin_right' => (int) ($input['page_margin_right'] ?? 15),
            'line_spacing' => (string) ($input['line_spacing'] ?? '1.15'),

            // Logos Base64
            'logo_pln' => $this->fileToBase64(public_path('logo/sidebar-logo.png')),
            'logo_k3' => $this->fileToBase64(public_path('logo/k3.png')),
        ];
    }

    /**
     * Render the PDF / HTML view.
     *
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        return view('har.formulir.combustion-pressure-pdf', ['data' => $data])->render();
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

        return "data:{$mime};base64," . base64_encode($content);
    }
}
