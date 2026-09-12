<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\EmergencyEquipment;
use App\Models\EmergencyFacilityCheck;
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
 * Emergency-facility readiness checks (modul K3). One row per emergency
 * equipment from the master, for a unit/month and period (monthly or a week
 * 1-4). Readiness % is derived from ready/total on read, never forced when the
 * source is non-numeric.
 */
class EmergencyFacilityController extends Controller
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
        $week = $request->query('week') === null || $request->query('week') === 'bulanan'
            ? null
            : (int) $request->integer('week');

        $equipments = EmergencyEquipment::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        $stored = EmergencyFacilityCheck::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->where(fn ($q) => $week === null ? $q->whereNull('week') : $q->where('week', $week))
            ->get()->keyBy('emergency_equipment_id');

        $rows = $equipments->map(function (EmergencyEquipment $equipment) use ($stored): array {
            $check = $stored->get($equipment->id);
            $total = (int) ($check?->jml_total ?? 0);
            $ready = (int) ($check?->jml_ready ?? 0);

            return [
                'equipment_id' => $equipment->id,
                'equipment_name' => trim(($equipment->group_name ? $equipment->group_name.' — ' : '').$equipment->name),
                'jml_total' => $check?->jml_total ?? 0,
                'jml_ready' => $check?->jml_ready ?? 0,
                'jml_not_ready' => $check?->jml_not_ready ?? 0,
                'persen_kesiapan' => $total > 0 ? round($ready / $total * 100, 1).'%' : '—',
                'kendala' => $check?->kendala,
                'tindak_lanjut' => $check?->tindak_lanjut,
            ];
        })->all();

        return Inertia::render('k3/input/emergency', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'week' => $week === null ? 'bulanan' : (string) $week],
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'weeks' => [
                    ['value' => 'bulanan', 'label' => 'Bulanan'],
                    ['value' => '1', 'label' => 'Minggu 1'],
                    ['value' => '2', 'label' => 'Minggu 2'],
                    ['value' => '3', 'label' => 'Minggu 3'],
                    ['value' => '4', 'label' => 'Minggu 4'],
                ],
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
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'week' => ['nullable'],
            'rows' => ['array'],
            'rows.*.equipment_id' => ['required', 'integer', Rule::exists('emergency_equipments', 'id')->where('unit_id', $unit->id)],
            'rows.*.jml_total' => ['nullable', 'integer', 'min:0'],
            'rows.*.jml_ready' => ['nullable', 'integer', 'min:0'],
            'rows.*.jml_not_ready' => ['nullable', 'integer', 'min:0'],
            'rows.*.kendala' => ['nullable', 'string', 'max:255'],
            'rows.*.tindak_lanjut' => ['nullable', 'string', 'max:255'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $week = ($validated['week'] ?? 'bulanan') === 'bulanan' || $validated['week'] === null
            ? null
            : (int) $validated['week'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $week, $user): void {
            foreach ($validated['rows'] ?? [] as $row) {
                EmergencyFacilityCheck::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'emergency_equipment_id' => (int) $row['equipment_id'], 'year' => $year, 'month' => $month, 'week' => $week],
                    [
                        'jml_total' => (int) ($row['jml_total'] ?? 0),
                        'jml_ready' => (int) ($row['jml_ready'] ?? 0),
                        'jml_not_ready' => (int) ($row['jml_not_ready'] ?? 0),
                        'kendala' => $row['kendala'] ?? null,
                        'tindak_lanjut' => $row['tindak_lanjut'] ?? null,
                        'input_by' => $user->id,
                    ],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan kesiapan fasilitas darurat {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kesiapan fasilitas darurat disimpan.']);

        return back();
    }
}
