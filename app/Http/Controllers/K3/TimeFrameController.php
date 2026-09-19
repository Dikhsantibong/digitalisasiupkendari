<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
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
 * Time Frame (modul K3): the plan-vs-realisation matrix of K3 activities over
 * the days of a month. Each activity shows two timeline rows — RENC (rencana)
 * and REAL (realisasi); a day mark is stored as JSON day→"1" on the plan row
 * ({@see K3ActivityPlan::$plan_days} / {@see K3ActivityPlan::$real_days}), so the
 * dashboard S-curve keeps reading the same shape. Target = jumlah rencana,
 * realisasi = jumlah realisasi, kinerja = realisasi ÷ target.
 */
class TimeFrameController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $days = $this->buildDays($year, $month);

        $types = K3ActivityType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $stored = K3ActivityPlan::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()->keyBy('k3_activity_type_id');

        $rows = $types->map(function (K3ActivityType $type) use ($stored): array {
            $plan = $stored->get($type->id);

            return [
                'activity_type_id' => $type->id,
                'uraian' => $type->name,
                'pic' => $plan?->pic ?? $type->default_pic ?? '',
                'rencana' => $this->mapToDays($plan?->plan_days),
                'realisasi' => $this->mapToDays($plan?->real_days),
                'keterangan' => $plan?->keterangan ?? '',
            ];
        })->all();

        return Inertia::render('k3/input/time-frame', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'days' => $days,
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
            ],
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
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.activity_type_id' => ['required', 'integer', Rule::exists('k3_activity_types', 'id')],
            'rows.*.pic' => ['nullable', 'string', 'max:255'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
            'rows.*.rencana' => ['array'],
            'rows.*.realisasi' => ['array'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            foreach ($validated['rows'] ?? [] as $row) {
                K3ActivityPlan::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'k3_activity_type_id' => (int) $row['activity_type_id']],
                    [
                        'plan_days' => $this->daysToMap($row['rencana'] ?? []),
                        'real_days' => $this->daysToMap($row['realisasi'] ?? []),
                        'pic' => $row['pic'] ?? null,
                        'keterangan' => $row['keterangan'] ?? null,
                        'input_by' => $user->id,
                    ],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Time Frame K3 & Lingkungan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Time Frame K3 & Lingkungan berhasil disimpan.']);

        return back();
    }

    /**
     * Convert a stored day→mark map to a sorted list of day numbers.
     *
     * @param  array<string, mixed>|null  $map
     * @return list<int>
     */
    private function mapToDays(?array $map): array
    {
        return collect($map ?? [])
            ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')
            ->keys()
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 1 && $d <= 31)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Convert a list of day numbers back to the stored day→"1" map, keeping the
     * S-curve-friendly shape used by the dashboard.
     *
     * @param  array<int, mixed>  $days
     * @return array<string, string>
     */
    private function daysToMap(array $days): array
    {
        return collect($days)
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 1 && $d <= 31)
            ->unique()
            ->mapWithKeys(fn (int $d): array => [(string) $d => '1'])
            ->all();
    }

    /**
     * @return list<array{day: int, dow: string, is_red: bool, holiday: string|null}>
     */
    private function buildDays(int $year, int $month): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        return collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);

            return [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_red' => $date->isSaturday() || $date->isSunday() || $holiday !== null,
                'holiday' => $holiday?->description,
            ];
        })->all();
    }
}
