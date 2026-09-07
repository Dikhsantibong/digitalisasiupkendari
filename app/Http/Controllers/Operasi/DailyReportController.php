<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operasi\DailyReportStoreRequest;
use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\OperasiCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Excel-like daily engine input grid (modul OPERASI, menu Input). Rows are
 * calendar days, columns are meter fields. Opening stands and every derived
 * column come from {@see OperasiCalculator} so no formula lives here.
 */
class DailyReportController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly OperasiCalculator $calculator,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView), 403);

        $units = Unit::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name']);

        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'fuel_type']);

        $engine = $machines->firstWhere('id', (int) $request->integer('engine_id')) ?? $machines->first();

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $grid = $engine === null
            ? ['rows' => [], 'summary' => []]
            : $this->calculator->buildDailyEngineGrid($engine, $month, $year);

        $period = $engine === null ? null : ReportPeriod::query()
            ->where('unit_id', $unit->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        return Inertia::render('operasi/input/daily-report', [
            'filters' => [
                'unit_id' => $unit->id,
                'engine_id' => $engine?->id,
                'month' => $month,
                'year' => $year,
            ],
            'grid' => $grid,
            'engine' => $engine === null ? null : [
                'id' => $engine->id,
                'name' => $engine->name,
                'fuel_type' => $engine->fuel_type?->value,
                'uses_mfo' => $engine->fuel_type?->usesMfo() ?? false,
            ],
            'period' => $period === null ? null : [
                'total_days' => $period->total_days,
                'locked' => $period->isLocked(),
            ],
            'options' => [
                'units' => $units->all(),
                'machines' => $machines->map(fn (Machine $machine): array => [
                    'id' => $machine->id,
                    'name' => $machine->name,
                    'uses_mfo' => $machine->fuel_type?->usesMfo() ?? false,
                ])->all(),
                'years' => range($year - 2, $year + 1),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(DailyReportStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $engine = Machine::query()
            ->where('unit_id', $unit->id)
            ->findOrFail($request->integer('engine_id'));

        $month = $request->integer('month');
        $year = $request->integer('year');

        $period = $this->resolvePeriod($unit->id, $month, $year);
        abort_if($period->isLocked(), 422, 'Periode ini sudah dikunci.');

        $usesMfo = $engine->fuel_type?->usesMfo() ?? false;

        DB::transaction(function () use ($request, $unit, $engine, $year, $month, $usesMfo, $user): void {
            foreach ($request->array('rows') as $row) {
                $date = Carbon::create($year, $month, (int) $row['day']);

                $attributes = collect($row)
                    ->only(OperasiCalculator::EDITABLE_FIELDS)
                    ->map(fn ($value) => $value === '' ? null : $value)
                    ->all();

                if (! $usesMfo) {
                    $attributes['flowmeter_mfo_stand_akhir'] = null;
                    $attributes['flowmeter_mfo_tambah_liter'] = null;
                }

                DailyEngineReport::query()->updateOrCreate(
                    ['engine_id' => $engine->id, 'report_date' => $date->toDateString()],
                    [...$attributes, 'unit_id' => $unit->id, 'input_by' => $user->id],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan input operasi {$engine->name} periode {$month}/{$year}",
            $engine,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Input operasi berhasil disimpan.']);

        return back();
    }

    private function resolvePeriod(int $unitId, int $month, int $year): ReportPeriod
    {
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;

        return ReportPeriod::query()->firstOrCreate(
            ['unit_id' => $unitId, 'month' => $month, 'year' => $year],
            ['total_days' => $totalDays, 'total_hours' => $totalDays * 24],
        );
    }
}
