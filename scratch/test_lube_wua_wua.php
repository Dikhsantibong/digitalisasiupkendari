<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarLubeQualityPdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;

$unit = Unit::first() ?? new Unit(['name' => 'ULPLTD WUA-WUA']);
$machine = Machine::first() ?? new Machine(['name' => '1', 'serial_number' => '']);

$builder = new HarLubeQualityPdfBuilder;
$data = $builder->buildData($unit, $machine, [
    'document_number' => 'FMKD-305-14.3.2.b-A3',
    'revision' => '',
    'effective_date' => '31 - 07 - 2024',
    'page_number' => '',
    'unit_sentral' => 'ULPLTD WUA-WUA',
    'machine_name' => '1',
    'machine_number' => '1',
    'serial_number' => '',
    'sample_point' => 'Sump Tank',
    'signature_location' => 'Kendari',
    'signature_date' => '31 Agustus 2026',
    'manager_ul_title' => 'Plh. Manager Unit Layanan ULPLTD Wua-Wua',
    'manager_ul_name' => 'SURYADI PRATAMA',
    'tl_har_title' => 'Team Leader Pemeliharaan',
    'tl_har_name' => 'SURYADI PRATAMA',
    'staff_har_title' => 'Staff Pemeliharaan',
    'staff_har_name' => 'MUHAMMAD ABDUL LIIZAL',
]);

$html = $builder->renderHtml($data);
$pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
file_put_contents(__DIR__.'/test_wua_wua_lube.pdf', $pdf->output());

$canvas = $pdf->getDomPDF()->getCanvas();
echo 'PAGE COUNT: '.$canvas->get_page_count().PHP_EOL;
echo "PDF written to scratch/test_wua_wua_lube.pdf\n";
