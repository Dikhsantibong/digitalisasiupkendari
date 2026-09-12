<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
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
 * Periodic APAR/APAB condition checks (modul K3). One row per extinguisher from
 * the master, with condition fields for the unit/month; upserted per tube.
 */
class FireExtinguisherCheckController extends Controller
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

        $extinguishers = FireExtinguisher::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('location')->get();

        $stored = FireExtinguisherCheck::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()->keyBy('fire_extinguisher_id');

        $rows = $extinguishers->map(function (FireExtinguisher $ext) use ($stored): array {
            $check = $stored->get($ext->id);

            return [
                'extinguisher_id' => $ext->id,
                'label' => trim(($ext->rfid ? $ext->rfid.' · ' : '').($ext->location ?? '').' '.($ext->jenis ? '('.$ext->jenis.')' : '')),
                'tgl_periksa' => $check?->tgl_periksa?->format('Y-m-d'),
                'kondisi_tabung' => $check?->kondisi_tabung,
                'kondisi_nozzle' => $check?->kondisi_nozzle,
                'indikator_tekanan' => $check?->indikator_tekanan,
                'kondisi_pin_segel' => $check?->kondisi_pin_segel,
                'exp_date' => $check?->exp_date?->format('Y-m-d'),
                'keterangan' => $check?->keterangan,
            ];
        })->all();

        return Inertia::render('k3/input/apar-checks', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
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
            'rows.*.extinguisher_id' => ['required', 'integer', Rule::exists('fire_extinguishers', 'id')->where('unit_id', $unit->id)],
            'rows.*.tgl_periksa' => ['nullable', 'date'],
            'rows.*.kondisi_tabung' => ['nullable', 'string', 'max:255'],
            'rows.*.kondisi_nozzle' => ['nullable', 'string', 'max:255'],
            'rows.*.indikator_tekanan' => ['nullable', 'string', 'max:255'],
            'rows.*.kondisi_pin_segel' => ['nullable', 'string', 'max:255'],
            'rows.*.exp_date' => ['nullable', 'date'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            foreach ($validated['rows'] ?? [] as $row) {
                FireExtinguisherCheck::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'fire_extinguisher_id' => (int) $row['extinguisher_id'], 'year' => $year, 'month' => $month],
                    [
                        'tgl_periksa' => $row['tgl_periksa'] ?? null,
                        'kondisi_tabung' => $row['kondisi_tabung'] ?? null,
                        'kondisi_nozzle' => $row['kondisi_nozzle'] ?? null,
                        'indikator_tekanan' => $row['indikator_tekanan'] ?? null,
                        'kondisi_pin_segel' => $row['kondisi_pin_segel'] ?? null,
                        'exp_date' => $row['exp_date'] ?? null,
                        'keterangan' => $row['keterangan'] ?? null,
                        'input_by' => $user->id,
                    ],
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan inspeksi APAR/APAB {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspeksi APAR disimpan.']);

        return back();
    }
}
