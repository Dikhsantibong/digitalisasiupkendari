<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3KondisiK3;
use App\Models\K3KondisiK3Meta;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\K3KondisiK3Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for Form Laporan Unsafe Action dan Unsafe Condition (Kondisi K3).
 */
class KondisiK3Controller extends Controller
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

        $saved = K3KondisiK3::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($saved->isEmpty()) {
            $rows = K3KondisiK3Form::defaultRows();
            $hasSaved = false;
        } else {
            $rows = $saved->map(fn (K3KondisiK3 $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?? (string) ($idx + 1),
                'periode' => $r->periode ?? '-',
                'kategori' => $r->kategori ?? 'Unsafe Action',
                'temuan' => $r->temuan ?? '',
                'kondisi' => $r->kondisi ?? '',
                'tindak_lanjut' => $r->tindak_lanjut ?? '',
                'rekomendasi' => $r->rekomendasi ?? '',
                'lokasi' => $r->lokasi ?? '',
                'keterangan' => $r->keterangan ?? 'Open',
                'eviden_sebelum' => $r->eviden_sebelum,
                'eviden_sesudah' => $r->eviden_sesudah,
                'sort_order' => $r->sort_order,
            ])->all();
            $hasSaved = true;
        }

        $meta = K3KondisiK3Meta::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        return Inertia::render('k3/input/kondisi-k3', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
                'categories' => K3KondisiK3Form::KATEGORI,
                'statuses' => K3KondisiK3Form::STATUS,
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
            'rows.*.no_urut' => ['nullable', 'string', 'max:50'],
            'rows.*.periode' => ['nullable', 'string', 'max:100'],
            'rows.*.kategori' => ['required', 'string', 'max:100'],
            'rows.*.temuan' => ['required', 'string'],
            'rows.*.kondisi' => ['nullable', 'string'],
            'rows.*.tindak_lanjut' => ['nullable', 'string'],
            'rows.*.rekomendasi' => ['nullable', 'string'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:255'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            'rows.*.eviden_sebelum' => ['nullable', 'string', 'max:1000'],
            'rows.*.eviden_sesudah' => ['nullable', 'string', 'max:1000'],
            'rows.*.sort_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'meta.catatan' => ['nullable', 'string', 'max:3000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($unit, $month, $year, $validated, $user): void {
            K3KondisiK3::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach ($validated['rows'] as $index => $row) {
                // Filter out empty placeholder rows
                if (trim($row['temuan']) === '' && trim($row['kondisi'] ?? '') === '' && empty($row['eviden_sebelum']) && empty($row['eviden_sesudah'])) {
                    continue;
                }

                K3KondisiK3::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $row['no_urut'] ?? (string) ($index + 1),
                    'periode' => $row['periode'] ?? '-',
                    'kategori' => $row['kategori'],
                    'temuan' => $row['temuan'],
                    'kondisi' => $row['kondisi'] ?? null,
                    'tindak_lanjut' => $row['tindak_lanjut'] ?? null,
                    'rekomendasi' => $row['rekomendasi'] ?? null,
                    'lokasi' => $row['lokasi'] ?? null,
                    'keterangan' => $row['keterangan'] ?? 'Open',
                    'eviden_sebelum' => $row['eviden_sebelum'] ?? null,
                    'eviden_sesudah' => $row['eviden_sesudah'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'input_by' => $user->id,
                ]);
            }

            if (isset($validated['meta'])) {
                K3KondisiK3Meta::query()->updateOrCreate(
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
            "Menyimpan Laporan Unsafe Action dan Unsafe Condition (Kondisi K3) {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return redirect()
            ->route('k3.input.kondisi-k3.index', [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Laporan Unsafe Action dan Unsafe Condition berhasil disimpan.',
            ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('k3/kondisi-k3', 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'url' => $url,
            'path' => $path,
        ]);
    }
}
