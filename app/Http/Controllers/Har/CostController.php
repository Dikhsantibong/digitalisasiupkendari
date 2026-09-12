<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceCost;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maintenance cost accumulation (modul HAR). Costs default to the sum of the
 * period's Work Order costs; a monthly manual override in maintenance_costs
 * takes over when {@see MaintenanceCost::$use_manual} is set. The screen also
 * shows the year-to-date running total.
 */
class CostController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $auto = $this->autoCost($unit->id, $month, $year);
        $manual = MaintenanceCost::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->first();

        $effective = $this->effectiveCost($unit->id, $month, $year);

        return Inertia::render('har/input/costs', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'auto' => $auto,
            'manual' => [
                'service_cost' => $manual?->service_cost,
                'material_cost' => $manual?->material_cost,
                'use_manual' => (bool) ($manual?->use_manual ?? false),
                'keterangan' => $manual?->keterangan,
            ],
            'effective' => $effective,
            'ytd' => $this->yearToDate($unit->id, $month, $year),
            'options' => ['units' => $units->all(), 'years' => range($year - 3, $year + 1)],
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'service_cost' => ['nullable', 'numeric', 'min:0'],
            'material_cost' => ['nullable', 'numeric', 'min:0'],
            'use_manual' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        MaintenanceCost::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'year' => (int) $validated['year'], 'month' => (int) $validated['month']],
            [
                'service_cost' => $validated['service_cost'] ?? null,
                'material_cost' => $validated['material_cost'] ?? null,
                'use_manual' => $validated['use_manual'],
                'keterangan' => $validated['keterangan'] ?? null,
                'input_by' => $user->id,
            ],
        );

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan biaya pemeliharaan {$unit->name} {$validated['month']}/{$validated['year']}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Biaya pemeliharaan disimpan.']);

        return back();
    }

    /**
     * @return array{service: float, material: float, total: float}
     */
    private function autoCost(int $unitId, int $month, int $year): array
    {
        $periodId = ReportPeriod::query()
            ->where('unit_id', $unitId)->where('month', $month)->where('year', $year)->value('id');

        $query = WorkOrder::query()->where('unit_id', $unitId);
        $periodId === null ? $query->whereRaw('1 = 0') : $query->where('report_period_id', $periodId);

        $service = (float) (clone $query)->sum('service_cost');
        $material = (float) (clone $query)->sum('material_cost');

        return ['service' => $service, 'material' => $material, 'total' => $service + $material];
    }

    /**
     * @return array{service: float, material: float, total: float, source: string}
     */
    private function effectiveCost(int $unitId, int $month, int $year): array
    {
        $manual = MaintenanceCost::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->first();

        if ($manual !== null && $manual->use_manual) {
            $service = (float) ($manual->service_cost ?? 0);
            $material = (float) ($manual->material_cost ?? 0);

            return ['service' => $service, 'material' => $material, 'total' => $service + $material, 'source' => 'manual'];
        }

        return [...$this->autoCost($unitId, $month, $year), 'source' => 'auto'];
    }

    private function yearToDate(int $unitId, int $month, int $year): float
    {
        $total = 0.0;
        for ($m = 1; $m <= $month; $m++) {
            $total += $this->effectiveCost($unitId, $m, $year)['total'];
        }

        return round($total, 2);
    }
}
