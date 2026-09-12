<?php

namespace App\Http\Controllers\K3;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Laporan (modul K3): entry point to the editable full report document.
 */
class LaporanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3LaporanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();

        return Inertia::render('k3/laporan/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => (int) ($request->integer('month') ?: $now->month),
                'year' => (int) ($request->integer('year') ?: $now->year),
            ],
            'options' => ['units' => $units->all(), 'years' => range($now->year - 3, $now->year + 1)],
        ]);
    }
}
