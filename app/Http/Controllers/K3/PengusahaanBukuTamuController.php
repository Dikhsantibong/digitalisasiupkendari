<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanBukuTamu;
use App\Models\K3PengusahaanBukuTamuItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanBukuTamuController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        if ($request->filled('unit_id')) {
            $unit = Unit::query()->findOrFail($request->integer('unit_id'));
            abort_unless($user->canAccessUnit($unit), 403);
        } else {
            $unit = $units->first();
        }

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        $record = K3PengusahaanBukuTamu::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record && $record->items->isNotEmpty()) {
            $itemsData = $record->items->map(fn (K3PengusahaanBukuTamuItem $item, int $idx): array => [
                'id' => $item->id,
                'no_urut' => $item->no_urut ?? ($idx + 1),
                'tanggal' => $item->tanggal,
                'jumlah_kehadiran_tamu' => $item->jumlah_kehadiran_tamu,
                'tamu_pln' => $item->tamu_pln,
                'instansi' => $item->instansi,
                'kontraktor' => $item->kontraktor,
                'lainnya' => $item->lainnya,
                'keterangan' => $item->keterangan ?? '',
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $noDokumen = $record->no_dokumen;
            $revisi = $record->revisi;
            $tanggalDokumen = $record->tanggal_dokumen;
            $catatan = $record->catatan ?? '';
        } else {
            $itemsData = K3PengusahaanBukuTamu::buildDefaultRows($year, $month);
            $noDokumen = 'SMT-FM-AK3-06.04';
            $revisi = '01';
            $tanggalDokumen = '23 SEPTEMBER 2019';
            $catatan = '';
        }

        return Inertia::render('pengusahaan/k3/buku-tamu/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'record' => [
                'no_dokumen' => $noDokumen,
                'revisi' => $revisi,
                'tanggal_dokumen' => $tanggalDokumen,
                'catatan' => $catatan,
                'items' => $itemsData,
            ],
            'sample_items' => K3PengusahaanBukuTamu::buildSampleRows($year, $month),
            'has_saved' => $hasSaved,
            'can_write' => $user->hasPermissionTo(PermissionName::K3PengusahaanWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'no_dokumen' => ['nullable', 'string', 'max:100'],
            'revisi' => ['nullable', 'string', 'max:50'],
            'tanggal_dokumen' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.no_urut' => ['required', 'integer'],
            'items.*.tanggal' => ['required', 'string', 'max:50'],
            'items.*.jumlah_kehadiran_tamu' => ['required', 'integer', 'min:0'],
            'items.*.tamu_pln' => ['nullable', 'integer', 'min:0'],
            'items.*.instansi' => ['nullable', 'integer', 'min:0'],
            'items.*.kontraktor' => ['nullable', 'integer', 'min:0'],
            'items.*.lainnya' => ['nullable', 'integer', 'min:0'],
            'items.*.keterangan' => ['nullable', 'string'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $unit, $user): void {
            $record = K3PengusahaanBukuTamu::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'no_dokumen' => $validated['no_dokumen'] ?? 'SMT-FM-AK3-06.04',
                    'revisi' => $validated['revisi'] ?? '01',
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? '23 SEPTEMBER 2019',
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );

            $record->items()->delete();

            $itemsToCreate = array_map(fn (array $item, int $idx): array => [
                'no_urut' => $item['no_urut'] ?? ($idx + 1),
                'tanggal' => $item['tanggal'],
                'jumlah_kehadiran_tamu' => (int) ($item['jumlah_kehadiran_tamu'] ?? 0),
                'tamu_pln' => (int) ($item['tamu_pln'] ?? 0),
                'instansi' => (int) ($item['instansi'] ?? 0),
                'kontraktor' => (int) ($item['kontraktor'] ?? 0),
                'lainnya' => (int) ($item['lainnya'] ?? 0),
                'keterangan' => $item['keterangan'] ?? null,
                'sort_order' => $item['sort_order'] ?? $idx,
            ], $validated['items'], array_keys($validated['items']));

            $record->items()->createMany($itemsToCreate);

            $this->activityLogger->log(
                ActivityEvent::Created,
                "Menyimpan laporan mutasi buku tamu ({$record->no_dokumen}) unit {$unit->name} periode {$validated['month']}/{$validated['year']} dengan ".count($itemsToCreate).' baris entri.',
                $record,
            );
        });

        return redirect()->back()->with('success', 'Data laporan mutasi buku tamu berhasil disimpan.');
    }
}
