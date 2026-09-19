<?php

use App\Http\Controllers\Pdm\Program5s5rController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::first();
$req = Request::create('/pdm/jadwal/program-5s-5r', 'GET', ['month' => 9, 'unit_id' => 3, 'year' => 2026]);
$req->setUserResolver(fn () => $user);

$ctrl = app(Program5s5rController::class);
$res = $ctrl->index($req);
$props = $res->toResponse($req)->getOriginalContent()['page']['props'];

echo 'Rows count: '.count($props['rows'])."\n";
foreach ($props['rows'] as $r) {
    echo 'Uraian: '.$r['uraian'].' | Target: '.$r['target']."\n";
}
echo 'Days count: '.count($props['days'])."\n";
