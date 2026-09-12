<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Har\HarReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Laporan (modul HAR): the monthly maintenance report and the executive
 * summary. Both are computed by {@see HarReportBuilder} and previewed for
 * print-to-PDF.
 */
class LaporanController extends Controller
{
    public function __construct(private readonly HarReportBuilder $builder) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarLaporanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();

        return Inertia::render('har/laporan/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => (int) ($request->integer('month') ?: $now->month),
                'year' => (int) ($request->integer('year') ?: $now->year),
            ],
            'options' => ['units' => $units->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'can_executive' => $user->hasPermissionTo(PermissionName::HarExecutiveView),
        ]);
    }

    public function monthly(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarLaporanView), 403);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        return Inertia::render('har/laporan/monthly', [
            'data' => $this->builder->monthly($unit, $month, $year),
        ]);
    }

    public function executive(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarExecutiveView), 403);

        $unit = $this->resolveUnit($request);
        [$month, $year] = [(int) $request->integer('month'), (int) $request->integer('year')];

        return Inertia::render('har/laporan/executive', [
            'data' => $this->builder->executive($unit, $month, $year),
        ]);
    }

    private function resolveUnit(Request $request): Unit
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        return $unit;
    }
}
