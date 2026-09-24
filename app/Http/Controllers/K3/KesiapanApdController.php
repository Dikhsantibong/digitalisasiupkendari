<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3KesiapanApd;
use App\Models\K3KesiapanApdMeta;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\K3KesiapanApdForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for Form Inspeksi K3 / Kesiapan APD (Modul K3 & Keselamatan Kerja).
 */
class KesiapanApdController extends Controller
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

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = $units->firstWhere('id', $unitId) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        $saved = K3KesiapanApd::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($saved->isEmpty()) {
            $rows = K3KesiapanApdForm::defaultRows();
            $hasSaved = false;
        } else {
            $rows = $saved->map(fn (K3KesiapanApd $r, int $idx): array => [
                'id' => $r->id,
                'kelompok' => $r->kelompok,
                'no_urut' => $r->no_urut ?? (string) ($idx + 1),
                'inspeksi' => $r->inspeksi,
                'apd_jumlah' => $r->apd_jumlah ?? '-',
                'kelayakan_apd' => $r->kelayakan_apd ?? '-',
                'peralatan_jumlah' => $r->peralatan_jumlah ?? '-',
                'peralatan_kelayakan' => $r->peralatan_kelayakan ?? '-',
                'sop_pnp' => $r->sop_pnp ?? '-',
                'sop_vendor' => $r->sop_vendor ?? '-',
                'p3k_ada' => $r->p3k_ada ?? '-',
                'p3k_memenuhi' => $r->p3k_memenuhi ?? '-',
                'cara_kerja' => $r->cara_kerja ?? '-',
                'keterangan' => $r->keterangan ?? '',
                'sort_order' => $r->sort_order,
            ])->all();
            $hasSaved = true;
        }

        $meta = K3KesiapanApdMeta::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        return Inertia::render('k3/input/kesiapan-apd', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'groups' => K3KesiapanApdForm::GROUPS,
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
                'answers' => K3KesiapanApdForm::OPTIONS,
            ],
            'rows' => $rows,
            'meta' => [
                'catatan' => $meta?->catatan ?? '',
            ],
            'has_saved' => $hasSaved,
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array'],
            'rows.*.kelompok' => ['required', 'string', 'max:150'],
            'rows.*.no_urut' => ['nullable', 'string', 'max:20'],
            'rows.*.inspeksi' => ['required', 'string', 'max:255'],
            'rows.*.apd_jumlah' => ['nullable', 'string', 'max:50'],
            'rows.*.kelayakan_apd' => ['nullable', 'string', 'max:50'],
            'rows.*.peralatan_jumlah' => ['nullable', 'string', 'max:50'],
            'rows.*.peralatan_kelayakan' => ['nullable', 'string', 'max:50'],
            'rows.*.sop_pnp' => ['nullable', 'string', 'max:50'],
            'rows.*.sop_vendor' => ['nullable', 'string', 'max:50'],
            'rows.*.p3k_ada' => ['nullable', 'string', 'max:50'],
            'rows.*.p3k_memenuhi' => ['nullable', 'string', 'max:50'],
            'rows.*.cara_kerja' => ['nullable', 'string', 'max:50'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            'rows.*.sort_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'meta.catatan' => ['nullable', 'string', 'max:3000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($unit, $month, $year, $validated, $user): void {
            K3KesiapanApd::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach ($validated['rows'] as $index => $row) {
                K3KesiapanApd::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'kelompok' => $row['kelompok'],
                    'no_urut' => $row['no_urut'] ?? null,
                    'inspeksi' => $row['inspeksi'],
                    'apd_jumlah' => $row['apd_jumlah'] ?? '-',
                    'kelayakan_apd' => $row['kelayakan_apd'] ?? '-',
                    'peralatan_jumlah' => $row['peralatan_jumlah'] ?? '-',
                    'peralatan_kelayakan' => $row['peralatan_kelayakan'] ?? '-',
                    'sop_pnp' => $row['sop_pnp'] ?? '-',
                    'sop_vendor' => $row['sop_vendor'] ?? '-',
                    'p3k_ada' => $row['p3k_ada'] ?? '-',
                    'p3k_memenuhi' => $row['p3k_memenuhi'] ?? '-',
                    'cara_kerja' => $row['cara_kerja'] ?? '-',
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'input_by' => $user->id,
                ]);
            }

            if (isset($validated['meta'])) {
                K3KesiapanApdMeta::query()->updateOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'catatan' => $validated['meta']['catatan'] ?? null,
                    ]
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Form Inspeksi K3 (Kesiapan APD) {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return redirect()
            ->route('k3.input.kesiapan-apd.index', [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data Kesiapan APD & Inspeksi K3 berhasil disimpan.',
            ]);
    }
}
