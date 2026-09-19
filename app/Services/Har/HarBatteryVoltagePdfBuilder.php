<?php

namespace App\Services\Har;

use App\Models\Employee;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class HarBatteryVoltagePdfBuilder
{
    public const DEFAULT_CHARGING_CONDITIONS = [
        ['mode' => 'FLOATING', 'item' => 'Rectifier', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'FLOATING', 'item' => 'Load', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'FLOATING', 'item' => 'Battery', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'EQUILIZING', 'item' => 'Rectifier', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'EQUILIZING', 'item' => 'Load', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'EQUILIZING', 'item' => 'Battery', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'BOOSTING', 'item' => 'Rectifier', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'BOOSTING', 'item' => 'Load', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
        ['mode' => 'BOOSTING', 'item' => 'Battery', 'cond_24v' => '', 'cond_110v' => '', 'notes' => ''],
    ];

    /**
     * @return array<int, array{cell: int, voltage: string}>
     */
    public static function defaultCells24v(): array
    {
        $cells = [];
        for ($i = 1; $i <= 12; $i++) {
            $cells[] = ['cell' => $i, 'voltage' => ''];
        }

        return $cells;
    }

    /**
     * @return array<int, array{cell: int, voltage: string}>
     */
    public static function defaultCells110v(): array
    {
        $cells = [];
        for ($i = 1; $i <= 55; $i++) {
            $cells[] = ['cell' => $i, 'voltage' => ''];
        }

        return $cells;
    }

    /**
     * @return array<int, array{cell: int, voltage: string}>
     */
    public static function sampleScanCells24v(): array
    {
        $cells = [];
        for ($i = 1; $i <= 12; $i++) {
            $cells[] = ['cell' => $i, 'voltage' => '2.2'];
        }

        return $cells;
    }

    /**
     * @return array<int, array{cell: int, voltage: string}>
     */
    public static function sampleScanCells110v(): array
    {
        $values = [
            // 1-7
            1 => '2.1', 2 => '2.2', 3 => '2.2', 4 => '2.2', 5 => '2.2', 6 => '2.1', 7 => '2.1',
            // 8-14
            8 => '2.1', 9 => '2.2', 10 => '2.1', 11 => '2.1', 12 => '2.1', 13 => '2.1', 14 => '2.1',
            // 15-21
            15 => '2.1', 16 => '2.2', 17 => '2.2', 18 => '2.1', 19 => '2.2', 20 => '2.1', 21 => '2.2',
            // 22-28
            22 => '2.2', 23 => '2.1', 24 => '2.1', 25 => '2.1', 26 => '2.1', 27 => '2.2', 28 => '2.2',
            // 29-35
            29 => '2.1', 30 => '2.1', 31 => '2.1', 32 => '2.2', 33 => '2.1', 34 => '2.1', 35 => '2.2',
            // 36-42
            36 => '2.2', 37 => '2.1', 38 => '2.1', 39 => '2.2', 40 => '2.1', 41 => '2.2', 42 => '2.2',
            // 43-49
            43 => '2.1', 44 => '2.2', 45 => '2.2', 46 => '2.1', 47 => '2.1', 48 => '2.1', 49 => '2.1',
            // 50-55
            50 => '2.1', 51 => '2.1', 52 => '2.2', 53 => '2.2', 54 => '2.1', 55 => '2.1',
        ];

        $cells = [];
        for ($i = 1; $i <= 55; $i++) {
            $cells[] = ['cell' => $i, 'voltage' => $values[$i] ?? '2.1'];
        }

        return $cells;
    }

    /**
     * @param  array<int, array{cell: int, voltage: string}>  $cells
     * @return array{max: string, min: string, total: string}
     */
    public static function calculateSummary(array $cells): array
    {
        $voltages = [];
        foreach ($cells as $c) {
            $v = str_replace(',', '.', trim($c['voltage'] ?? ''));
            if ($v !== '' && is_numeric($v)) {
                $voltages[] = (float) $v;
            }
        }
        if (empty($voltages)) {
            return ['max' => '', 'min' => '', 'total' => ''];
        }

        return [
            'max' => (string) max($voltages),
            'min' => (string) min($voltages),
            'total' => (string) round(array_sum($voltages), 2),
        ];
    }

    /**
     * Build view data for the Battery Voltage PDF and HTML document.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildData(Unit $unit, ?Machine $machine = null, array $input = []): array
    {
        $testDate = ! empty($input['test_date']) ? Carbon::parse($input['test_date']) : Carbon::today();

        // 24V resolution
        $cells24v = $input['cells_24v'] ?? [];
        if (empty($cells24v)) {
            $cells24v = self::defaultCells24v();
        }
        $summary24v = $input['summary_24v'] ?? self::calculateSummary($cells24v);

        // 110V resolution
        $cells110v = $input['cells_110v'] ?? [];
        if (empty($cells110v)) {
            $cells110v = self::defaultCells110v();
        }
        $summary110v = $input['summary_110v'] ?? self::calculateSummary($cells110v);

        // Charging conditions resolution
        $chargingConditions = $input['charging_conditions'] ?? [];
        if (empty($chargingConditions)) {
            $chargingConditions = self::DEFAULT_CHARGING_CONDITIONS;
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
            'machine_number' => $input['machine_number'] ?? ($machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #'], '', $machine->name) : '1,2,3'),
            'installed_power' => $input['installed_power'] ?? ($machine?->capacity_kw ?? '2544'),
            'capable_power' => $input['capable_power'] ?? '',
            'rpm' => $input['rpm'] ?? '600',

            // 24V data & summary
            'cells_24v' => $cells24v,
            'summary_24v' => $summary24v,

            // 110V data & summary
            'cells_110v' => $cells110v,
            'summary_110v' => $summary110v,

            // Charging conditions & Notes
            'charging_conditions' => $chargingConditions,
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
            'page_margin_top' => (int) ($input['page_margin_top'] ?? 8),
            'page_margin_bottom' => (int) ($input['page_margin_bottom'] ?? 8),
            'page_margin_left' => (int) ($input['page_margin_left'] ?? 10),
            'page_margin_right' => (int) ($input['page_margin_right'] ?? 10),
            'line_spacing' => (string) ($input['line_spacing'] ?? '1.1'),

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
        return view('har.formulir.battery-voltage-pdf', ['data' => $data])->render();
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
