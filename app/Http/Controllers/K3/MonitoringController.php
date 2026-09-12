<?php

namespace App\Http\Controllers\K3;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\K3\K3MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Monitoring (modul K3): the running-month status dashboard — certificate &
 * extinguisher expiry badges (aktif / mendekati / expired) and a summary of the
 * period. Read-only; open to TL K3 and Manager UL.
 */
class MonitoringController extends Controller
{
    public function __construct(private readonly K3MonitoringService $monitoring) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3MonitoringView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        return Inertia::render('k3/monitoring/index', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => ['units' => $units->all(), 'years' => range($year - 3, $year + 1)],
            'data' => $this->monitoring->dashboard($unit, $month, $year),
        ]);
    }
}
