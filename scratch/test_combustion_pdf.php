<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$unit = App\Models\Unit::with('serviceUnit')->first();
$machine = App\Models\Machine::where('unit_id', $unit->id)->first();
$builder = app(App\Services\Har\HarCombustionPressurePdfBuilder::class);
$data = $builder->buildData($unit, $machine, []);
$html = $builder->renderHtml($data);
file_put_contents('scratch/test_combustion.html', $html);
$pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
file_put_contents('scratch/test_combustion.pdf', $pdf->output());

$pdfDoc = new setasign\Fpdi\Fpdi();
$pageCount = $pdfDoc->setSourceFile('scratch/test_combustion.pdf');
echo "Combustion PDF generated! Page count: " . $pageCount . "\n";
