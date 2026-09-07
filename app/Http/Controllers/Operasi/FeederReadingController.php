<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operasi\FeederReadingStoreRequest;
use App\Models\DailyFeederReading;
use App\Models\Feeder;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily feeder meter readings (modul OPERASI, menu Input): rows are calendar
 * days, columns are the unit's feeders. Only the closing stand is typed.
 */
class FeederReadingController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $feeders = Feeder::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $stored = DailyFeederReading::query()
            ->whereIn('feeder_id', $feeders->pluck('id'))
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->get()
            ->groupBy('feeder_id');

        $rows = [];
        foreach (range(1, $totalDays) as $day) {
            $row = ['day' => $day, 'report_date' => Carbon::create($year, $month, $day)->toDateString()];
            foreach ($feeders as $feeder) {
                $reading = ($stored[$feeder->id] ?? collect())
                    ->first(fn (DailyFeederReading $r): bool => (int) $r->report_date->day === $day);
                $row['feeder_'.$feeder->id] = $reading?->stand_akhir;
            }
            $rows[] = $row;
        }

        return Inertia::render('operasi/input/feeder-readings', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'feeders' => $feeders->all(),
            'rows' => $rows,
            'options' => ['units' => $units->all(), 'years' => range($year - 2, $year + 1)],
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(FeederReadingStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $month = $request->integer('month');
        $year = $request->integer('year');

        DB::transaction(function () use ($request, $unit, $year, $month, $user): void {
            foreach ($request->array('readings') as $reading) {
                $date = Carbon::create($year, $month, (int) $reading['day'])->toDateString();

                DailyFeederReading::query()->updateOrCreate(
                    ['feeder_id' => (int) $reading['feeder_id'], 'report_date' => $date],
                    [
                        'unit_id' => $unit->id,
                        'stand_akhir' => ($reading['stand_akhir'] ?? '') === '' ? null : $reading['stand_akhir'],
                        'input_by' => $user->id,
                    ],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan pembacaan feeder {$unit->name} periode {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembacaan feeder disimpan.']);

        return back();
    }
}
