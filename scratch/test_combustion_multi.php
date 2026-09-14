<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$unit = App\Models\Unit::with('serviceUnit')->first();
$machine = App\Models\Machine::where('unit_id', $unit->id)->first();
$builder = app(App\Services\Har\HarCombustionPressurePdfBuilder::class);

foreach ([6, 8, 12, 16] as $cyl) {
    $data = $builder->buildData($unit, $machine, ['cylinders_count' => $cyl]);
    $html = $builder->renderHtml($data);
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
    file_put_contents("scratch/test_combustion_{$cyl}.pdf", $pdf->output());

    $pdfDoc = new setasign\Fpdi\Fpdi();
    $pageCount = $pdfDoc->setSourceFile("scratch/test_combustion_{$cyl}.pdf");
    echo "Cylinders: {$cyl} -> Pages: {$pageCount}\n";
}
