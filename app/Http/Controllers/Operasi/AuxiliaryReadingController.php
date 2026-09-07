<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operasi\AuxiliaryReadingStoreRequest;
use App\Models\AuxiliarySource;
use App\Models\DailyAuxiliaryReading;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily auxiliary-source readings (modul OPERASI, menu Input): rows are days,
 * each source contributes a kWh and a BBM closing stand.
 */
class AuxiliaryReadingController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $sources = AuxiliarySource::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $stored = DailyAuxiliaryReading::query()
            ->whereIn('auxiliary_source_id', $sources->pluck('id'))
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->get()
            ->groupBy('auxiliary_source_id');

        $rows = [];
        foreach (range(1, $totalDays) as $day) {
            $row = ['day' => $day, 'report_date' => Carbon::create($year, $month, $day)->toDateString()];
            foreach ($sources as $source) {
                $reading = ($stored[$source->id] ?? collect())
                    ->first(fn (DailyAuxiliaryReading $r): bool => (int) $r->report_date->day === $day);
                $row['kwh_'.$source->id] = $reading?->stand_kwh_akhir;
                $row['bbm_'.$source->id] = $reading?->stand_bbm_akhir;
            }
            $rows[] = $row;
        }

        return Inertia::render('operasi/input/auxiliary-readings', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'sources' => $sources->all(),
            'rows' => $rows,
            'options' => ['units' => $units->all(), 'years' => range($year - 2, $year + 1)],
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(AuxiliaryReadingStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $month = $request->integer('month');
        $year = $request->integer('year');

        $nullable = fn ($value) => ($value ?? '') === '' ? null : $value;

        DB::transaction(function () use ($request, $unit, $year, $month, $user, $nullable): void {
            foreach ($request->array('readings') as $reading) {
                $date = Carbon::create($year, $month, (int) $reading['day'])->toDateString();

                DailyAuxiliaryReading::query()->updateOrCreate(
                    ['auxiliary_source_id' => (int) $reading['auxiliary_source_id'], 'report_date' => $date],
                    [
                        'unit_id' => $unit->id,
                        'stand_kwh_akhir' => $nullable($reading['stand_kwh_akhir'] ?? null),
                        'stand_bbm_akhir' => $nullable($reading['stand_bbm_akhir'] ?? null),
                        'input_by' => $user->id,
                    ],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan pembacaan pasokan cadangan {$unit->name} periode {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembacaan pasokan cadangan disimpan.']);

        return back();
    }
}
