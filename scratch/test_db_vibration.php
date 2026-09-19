<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\HarVibration;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Contracts\Console\Kernel;

echo "--- Testing DB Vibration Model ---\n";
$unit = Unit::first();
$machine = Machine::where('unit_id', $unit->id)->first();

if (! $unit || ! $machine) {
    echo "No unit or machine found!\n";
    exit(1);
}

echo "Unit: {$unit->name}, Machine: {$machine->name}\n";

$testDate = '2026-09-14';

// Test updateOrCreate
$record = HarVibration::updateOrCreate(
    [
        'unit_id' => $unit->id,
        'machine_id' => $machine->id,
        'test_date' => $testDate,
    ],
    [
        'document_number' => 'FMKD-314-10.3.3.a-B10',
        'revision' => '03',
        'effective_date' => '31 Juli 2024',
        'brand' => 'MAK',
        'model_type' => '8M 453 C',
        'installed_power' => '2800',
        'capable_power' => '1500 kW',
        'serial_number' => '',
        'machine_number' => '4',
        'rpm' => '600',
        'measurements' => HarVibration::sampleScanMeasurements(),
        'standard_text' => 'ISO 10816-6',
        'max_text' => 'Max 4.5 mm/s',
        'conclusion_text' => 'Vibrasi bearing dalam batas normal',
        'page_margin_top' => 8,
        'page_margin_bottom' => 8,
        'page_margin_left' => 10,
        'page_margin_right' => 10,
        'line_spacing' => '1.1',
        'format' => 'form',
    ]
);

echo "Successfully saved HarVibration ID: {$record->id}\n";
echo 'Measurements count: '.count($record->measurements)."\n";
echo 'Point 1 v_avg: '.($record->measurements[0]['v_avg'] ?? 'N/A')."\n";
echo 'Point 1 h_avg: '.($record->measurements[0]['h_avg'] ?? 'N/A')."\n";

// Clean up test record
$record->delete();
echo "Successfully cleaned up test record.\n";
echo "--- DB Test Passed! ---\n";
