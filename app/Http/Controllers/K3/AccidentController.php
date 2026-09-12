<?php

namespace App\Http\Controllers\K3;

use App\Enums\AccidentCategory;
use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\AccidentReport;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accident / occupational-disease reports (PAK/PAHK) for a unit/month. Most
 * months are NIHIL; the frontend offers a quick "Tandai NIHIL" that stores a
 * single nihil row. Saving replaces the period's rows wholesale.
 */
class AccidentController extends Controller
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

        $rows = AccidentReport::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('id')
            ->get()
            ->map(fn (AccidentReport $a): array => [
                'category' => $a->category->value,
                'incident_date' => $a->incident_date?->format('Y-m-d'),
                'fungsi' => $a->fungsi,
                'lokasi' => $a->lokasi,
                'luka_ringan' => $a->luka_ringan,
                'luka_berat' => $a->luka_berat,
                'meninggal' => $a->meninggal,
                'kerugian_material' => $a->kerugian_material,
                'is_nihil' => $a->is_nihil,
                'keterangan' => $a->keterangan,
            ])
            ->all();

        return Inertia::render('k3/input/accidents', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'categories' => collect(AccidentCategory::cases())->map(fn (AccidentCategory $c): array => ['value' => $c->value, 'label' => $c->label()])->all(),
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
            'rows' => ['array'],
            'rows.*.category' => ['required', new Enum(AccidentCategory::class)],
            'rows.*.incident_date' => ['nullable', 'date'],
            'rows.*.fungsi' => ['nullable', 'string', 'max:255'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:255'],
            'rows.*.luka_ringan' => ['nullable', 'integer', 'min:0'],
            'rows.*.luka_berat' => ['nullable', 'integer', 'min:0'],
            'rows.*.meninggal' => ['nullable', 'integer', 'min:0'],
            'rows.*.kerugian_material' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_nihil' => ['boolean'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            AccidentReport::query()
                ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
                ->delete();

            foreach ($validated['rows'] ?? [] as $row) {
                AccidentReport::query()->create([
                    'unit_id' => $unit->id, 'year' => $year, 'month' => $month,
                    'category' => $row['category'],
                    'incident_date' => $row['incident_date'] ?? null,
                    'fungsi' => $row['fungsi'] ?? null,
                    'lokasi' => $row['lokasi'] ?? null,
                    'luka_ringan' => (int) ($row['luka_ringan'] ?? 0),
                    'luka_berat' => (int) ($row['luka_berat'] ?? 0),
                    'meninggal' => (int) ($row['meninggal'] ?? 0),
                    'kerugian_material' => $row['kerugian_material'] ?? null,
                    'is_nihil' => (bool) ($row['is_nihil'] ?? false),
                    'keterangan' => $row['keterangan'] ?? null,
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan laporan kecelakaan K3 {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan kecelakaan disimpan.']);

        return back();
    }
}
