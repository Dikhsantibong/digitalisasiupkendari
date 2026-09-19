<?php

namespace App\Services\Har;

use App\Models\Employee;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class HarMotorCurrentPdfBuilder
{
    public const DEFAULT_MOTOR_ITEMS = [
        ['no' => 1, 'motor_name' => 'Radiator Air. 1', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 2, 'motor_name' => 'Radiator Air. 2', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 3, 'motor_name' => 'Radiator Air. 3', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 4, 'motor_name' => 'Radiator Oli. 1', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 5, 'motor_name' => 'Radiator Oli. 2', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 6, 'motor_name' => 'Jacket Cooling Water', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 7, 'motor_name' => 'Priming Pump', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 8, 'motor_name' => 'Circulation Oil Centrifugal ( COC )', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 9, 'motor_name' => 'Booster Module ( Supply.1 )', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 10, 'motor_name' => 'Booster Module ( Supply.2 )', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 11, 'motor_name' => 'Booster Module (Circ. Supply.1)', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 12, 'motor_name' => 'Booster Module (Circ. Supply.2)', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 13, 'motor_name' => 'Fly wheel', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 14, 'motor_name' => 'Vantilasi Ruangan', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
    ];

    public const SAMPLE_SCAN_ITEMS = [
        ['no' => 1, 'motor_name' => 'Radiator Air. 1', 'current_r' => '17.4', 'current_s' => '17.6', 'current_t' => '17.5', 'notes' => ''],
        ['no' => 2, 'motor_name' => 'Radiator Air. 2', 'current_r' => '17.3', 'current_s' => '17.5', 'current_t' => '18.6', 'notes' => ''],
        ['no' => 3, 'motor_name' => 'Radiator Air. 3', 'current_r' => '18.8', 'current_s' => '17.8', 'current_t' => '17.8', 'notes' => ''],
        ['no' => 4, 'motor_name' => 'Radiator Oli. 1', 'current_r' => '26.4', 'current_s' => '26.3', 'current_t' => '25.6', 'notes' => ''],
        ['no' => 5, 'motor_name' => 'Radiator Oli. 2', 'current_r' => '26.0', 'current_s' => '25.3', 'current_t' => '25.8', 'notes' => ''],
        ['no' => 6, 'motor_name' => 'Jacket Cooling Water', 'current_r' => '17.0', 'current_s' => '16.6', 'current_t' => '17.2', 'notes' => ''],
        ['no' => 7, 'motor_name' => 'Priming Pump', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 8, 'motor_name' => 'Circulation Oil Centrifugal ( COC )', 'current_r' => '11.9', 'current_s' => '11.4', 'current_t' => '11.3', 'notes' => ''],
        ['no' => 9, 'motor_name' => 'Booster Module ( Supply.1 )', 'current_r' => '1.4', 'current_s' => '1.3', 'current_t' => '1.3', 'notes' => ''],
        ['no' => 10, 'motor_name' => 'Booster Module ( Supply.2 )', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 11, 'motor_name' => 'Booster Module (Circ. Supply.1)', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 12, 'motor_name' => 'Booster Module (Circ. Supply.2)', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 13, 'motor_name' => 'Fly wheel', 'current_r' => '', 'current_s' => '', 'current_t' => '', 'notes' => ''],
        ['no' => 14, 'motor_name' => 'Vantilasi Ruangan', 'current_r' => '21.2', 'current_s' => '21.6', 'current_t' => '21.6', 'notes' => ''],
    ];

    /**
     * Build view data for the Motor Current PDF and HTML document.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildData(Unit $unit, ?Machine $machine = null, array $input = []): array
    {
        $testDate = ! empty($input['test_date']) ? Carbon::parse($input['test_date']) : Carbon::today();

        // Items resolution
        $items = $input['items'] ?? [];
        if (empty($items)) {
            $items = self::DEFAULT_MOTOR_ITEMS;
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
            'document_number' => $input['document_number'] ?? 'SMT-FM-KIT-02.07',
            'revision' => $input['revision'] ?? '01',
            'effective_date' => $input['effective_date'] ?? '13 Oktober 2021',
            'test_date' => $testDate->translatedFormat('d F Y'),
            'test_date_raw' => $testDate->format('Y-m-d'),

            // Technical specs
            'brand' => $input['brand'] ?? ($machine?->brand ?? 'MAK'),
            'model_type' => $input['model_type'] ?? ($machine?->type ?? '8M 453 AK'),
            'serial_number' => $input['serial_number'] ?? ($machine?->serial_number ?? ''),
            'machine_number' => $input['machine_number'] ?? ($machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #'], '', $machine->name) : '4'),
            'installed_power' => $input['installed_power'] ?? ($machine?->capacity_kw ?? '2544'),
            'capable_power' => $input['capable_power'] ?? '',
            'rpm' => $input['rpm'] ?? '600',

            // Items & Notes
            'items' => $items,
            'notes' => $input['notes'] ?? '',

            // Signatories
            'manager_ul_id' => $managerUl?->id,
            'manager_ul_name' => $input['manager_ul_name'] ?? ($managerUl?->name ?? 'Manager UL'),
            'manager_ul_title' => $input['manager_ul_title'] ?? ('Plh.Manager '.$ulLabel),
            'manager_ul_signature' => $this->resolveSignatureBase64($managerUl),

            'tl_har_id' => $tlHar?->id,
            'tl_har_name' => $input['tl_har_name'] ?? ($tlHar?->name ?? 'Team Leader Pemeliharaan'),
            'tl_har_title' => $input['tl_har_title'] ?? 'Team Leader Pemeliharaan',
            'tl_har_signature' => $this->resolveSignatureBase64($tlHar),

            'staff_har_id' => $staffHar?->id,
            'staff_har_name' => $input['staff_har_name'] ?? ($staffHar?->name ?? 'Staff Pemeliharaan'),
            'staff_har_title' => $input['staff_har_title'] ?? 'Staff Pemeliharaan',
            'staff_har_signature' => $this->resolveSignatureBase64($staffHar),

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
        return view('har.formulir.motor-current-pdf', ['data' => $data])->render();
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
