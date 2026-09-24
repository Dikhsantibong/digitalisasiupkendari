<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Pdm\PdmDocumentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Laporan (modul PdM & MATURITY LEVEL): pick unit & period, then open the
 * editable Laporan PdM & Maturity Level Pembangkit ({@see DocumentController}).
 */
class LaporanController extends Controller
{
    public function index(Request $request, PdmDocumentBuilder $builder): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::PdmLaporanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $request->filled('unit_id')
            ? Unit::query()->findOrFail($request->integer('unit_id'))
            : $units->first();
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        return Inertia::render('pdm/laporan/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            // Every table of the Laporan PdM, flagged when its data is saved for the period.
            'contents' => $builder->contents($unit, $month, $year),
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
        ]);
    }
}
