<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\PatrolLocation;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Security patrol log (modul K3): a matrix of checkpoints (POA…) × days of the
 * month, each cell the scan count for that checkpoint that day. The monthly
 * cumulative per checkpoint is summed for the recap.
 */
class PatrolController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        $days = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $locations = PatrolLocation::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('code')->get();

        $stored = SecurityPatrol::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('patrol_date', [Carbon::create($year, $month, 1)->toDateString(), Carbon::create($year, $month, $days)->toDateString()])
            ->get()
            ->groupBy('patrol_location_id');

        $rows = $locations->map(function (PatrolLocation $location) use ($stored, $days): array {
            $byDay = ($stored->get($location->id) ?? collect())->keyBy(fn (SecurityPatrol $p): int => (int) $p->patrol_date->day);
            $row = ['location_id' => $location->id, 'location_name' => $location->code.' — '.$location->name];
            for ($d = 1; $d <= $days; $d++) {
                $row['day_'.$d] = $byDay->get($d)?->total_scan;
            }

            return $row;
        })->all();

        return Inertia::render('k3/input/patrols', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'days' => $days,
            'rows' => $rows,
            'options' => ['units' => $units->all(), 'years' => range($year - 3, $year + 1)],
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.location_id' => ['required', 'integer', Rule::exists('patrol_locations', 'id')->where('unit_id', $unit->id)],
            'rows.*.days' => ['array'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            foreach ($validated['rows'] ?? [] as $row) {
                foreach ($row['days'] ?? [] as $day => $value) {
                    $date = Carbon::create($year, $month, (int) $day)->toDateString();
                    $count = is_numeric($value) ? (int) $value : 0;

                    if ($count <= 0) {
                        SecurityPatrol::query()
                            ->where('unit_id', $unit->id)
                            ->where('patrol_location_id', (int) $row['location_id'])
                            ->where('patrol_date', $date)
                            ->delete();

                        continue;
                    }

                    SecurityPatrol::query()->updateOrCreate(
                        ['unit_id' => $unit->id, 'patrol_location_id' => (int) $row['location_id'], 'patrol_date' => $date],
                        ['total_scan' => $count, 'input_by' => $user->id],
                    );
                }
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan patroli keamanan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Log patroli disimpan.']);

        return back();
    }
}
