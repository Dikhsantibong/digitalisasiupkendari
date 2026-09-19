<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\HarLubeQuality;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Contracts\Console\Kernel;

echo "--- Testing DB HarLubeQuality Persistence ---\n";
$unit = Unit::first();
$machine = Machine::where('unit_id', $unit->id)->first();

if (! $unit || ! $machine) {
    echo "No unit or machine found!\n";
    exit(1);
}

$testDate = '2026-09-14';

$record = HarLubeQuality::updateOrCreate(
    [
        'unit_id' => $unit->id,
        'machine_id' => $machine->id,
        'test_date' => $testDate,
    ],
    [
        'document_number' => 'FMKD-305-14.3.2.b-A3',
        'revision' => '',
        'effective_date' => '31 - 07 - 2024',
        'page_number' => '',
        'unit_sentral' => 'ULPLTD WUA-WUA',
        'machine_name' => '1',
        'machine_number' => '1',
        'serial_number' => '',
        'sample_point' => 'Sump Tank',
        'parameters' => HarLubeQuality::sampleScanParameters(),
        'status_text' => HarLubeQuality::defaultStatusText(),
        'standard_text' => HarLubeQuality::defaultStandardText(),
        'photo_path' => null,
        'photo_caption' => HarLubeQuality::defaultPhotoCaption(),
        'analisa_text' => HarLubeQuality::defaultAnalisaText(),
        'cba_text' => HarLubeQuality::defaultCbaText(),
        'rekomendasi_text' => HarLubeQuality::defaultRekomendasiText(),
        'signature_location' => 'Kendari',
        'signature_date' => '31 Agustus 2026',
        'page_margin_top' => 8,
        'page_margin_bottom' => 8,
        'page_margin_left' => 10,
        'page_margin_right' => 10,
        'line_spacing' => '1.15',
        'format' => 'form',
    ]
);

echo "Record saved ID: {$record->id}\n";
echo 'TBN value: '.($record->parameters[0]['tbn'] ?? 'N/A')."\n";
echo 'Water content: '.($record->parameters[0]['water_content'] ?? 'N/A')."\n";
echo "Status: {$record->status_text}\n";

$record->delete();
echo "Cleaned up record successfully.\n";
echo "--- DB HarLubeQuality Test Passed! ---\n";
