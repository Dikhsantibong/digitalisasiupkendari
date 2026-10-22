<?php

namespace App\Http\Controllers\K3;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\K3\K3DivisionFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekap 5S5R Seluruh Divisi (K3): the 5S5R inputs of Operasi, Pemeliharaan,
 * PdM and Logistik & Gudang for a unit and month, read from the divisions'
 * own pages ({@see K3DivisionFeed}). Read-only — each division keeps filling
 * its own page; the same rekap feeds the Laporan K3.
 */
class Rekap5s5rController extends Controller
{
    public function index(Request $request, K3DivisionFeed $divisions): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputView) || $user->hasPermissionTo(PermissionName::K3LaporanView), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail((int) ($request->integer('unit_id') ?: $units->first()->id));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = max(2000, min(2100, (int) ($request->integer('year') ?: $now->year)));

        $programs = $divisions->program5s5r($unit, $month, $year, $user);
        $rencana = (int) array_sum(array_map(fn (array $program): int => $program['total']['rencana'], array_filter($programs, fn (array $p): bool => $p['saved'])));
        $realisasi = (int) array_sum(array_map(fn (array $program): int => $program['total']['realisasi'], array_filter($programs, fn (array $p): bool => $p['saved'])));

        return Inertia::render('k3/input/rekap-5s5r/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'programs' => $programs,
            'summary' => [
                'divisions' => count($programs),
                'filled' => count(array_filter($programs, fn (array $p): bool => $p['saved'])),
                'rencana' => $rencana,
                'realisasi' => $realisasi,
                'kinerja' => K3DivisionFeed::percent($realisasi, $rencana),
            ],
        ]);
    }
}
