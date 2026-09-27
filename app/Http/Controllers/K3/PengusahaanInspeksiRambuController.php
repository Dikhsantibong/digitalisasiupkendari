<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanInspeksiRambu;
use App\Models\K3PengusahaanInspeksiRambuItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanInspeksiRambuController extends Controller
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

        $record = K3PengusahaanInspeksiRambu::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record && $record->items->isNotEmpty()) {
            $itemsData = $record->items->map(fn (K3PengusahaanInspeksiRambuItem $item, int $idx): array => [
                'id' => $item->id,
                'no_id' => $item->no_id ?? ($idx + 1),
                'rambu_k3' => $item->rambu_k3,
                'lokasi' => $item->lokasi,
                'tingkat_pelanggaran' => $item->tingkat_pelanggaran ?? 'Nihil',
                'keterangan' => $item->keterangan ?? 'Perhatian',
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $noDokumen = $record->no_dokumen;
            $revisi = $record->revisi;
            $tanggalDokumen = $record->tanggal_dokumen;
            $tanggalInspeksi = $record->tanggal_inspeksi ?? '';
            $catatan = $record->catatan ?? '';
        } else {
            // Check previous month for prefill
            $prevRecord = K3PengusahaanInspeksiRambu::query()
                ->with('items')
                ->where('unit_id', $unit->id)
                ->where(function ($q) use ($year, $month) {
                    $q->where('year', '<', $year)
                        ->orWhere(function ($q2) use ($year, $month) {
                            $q2->where('year', $year)->where('month', '<', $month);
                        });
                })
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();

            if ($prevRecord && $prevRecord->items->isNotEmpty()) {
                $itemsData = $prevRecord->items->map(fn (K3PengusahaanInspeksiRambuItem $item, int $idx): array => [
                    'id' => null,
                    'no_id' => $item->no_id ?? ($idx + 1),
                    'rambu_k3' => $item->rambu_k3,
                    'lokasi' => $item->lokasi,
                    'tingkat_pelanggaran' => 'Nihil',
                    'keterangan' => $item->keterangan ?? 'Perhatian',
                    'sort_order' => $idx,
                ])->all();
                $noDokumen = $prevRecord->no_dokumen;
                $revisi = $prevRecord->revisi;
                $tanggalDokumen = $prevRecord->tanggal_dokumen;
            } else {
                $itemsData = array_map(fn (array $it, int $idx): array => [
                    'id' => null,
                    'no_id' => $it['no_id'] ?? ($idx + 1),
                    'rambu_k3' => $it['rambu_k3'],
                    'lokasi' => $it['lokasi'],
                    'tingkat_pelanggaran' => $it['tingkat_pelanggaran'] ?? 'Nihil',
                    'keterangan' => $it['keterangan'] ?? 'Perhatian',
                    'sort_order' => $idx,
                ], K3PengusahaanInspeksiRambu::DEFAULT_ITEMS, array_keys(K3PengusahaanInspeksiRambu::DEFAULT_ITEMS));
                $noDokumen = 'SMT-FM-AK3-07.01';
                $revisi = '01';
                $tanggalDokumen = '23 September 2019';
            }

            $tanggalInspeksi = '';
            $catatan = '';
        }

        return Inertia::render('k3/pengusahaan/inspeksi-rambu/index', [
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
                'tanggal_inspeksi' => $tanggalInspeksi,
                'catatan' => $catatan,
                'items' => $itemsData,
            ],
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
            'tanggal_inspeksi' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.no_id' => ['required', 'integer'],
            'items.*.rambu_k3' => ['required', 'string', 'max:255'],
            'items.*.lokasi' => ['required', 'string', 'max:255'],
            'items.*.tingkat_pelanggaran' => ['nullable', 'string', 'max:100'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $unit, $user): void {
            $record = K3PengusahaanInspeksiRambu::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'no_dokumen' => $validated['no_dokumen'] ?? 'SMT-FM-AK3-07.01',
                    'revisi' => $validated['revisi'] ?? '01',
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? '23 September 2019',
                    'tanggal_inspeksi' => $validated['tanggal_inspeksi'] ?? null,
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );

            $record->items()->delete();

            $itemsToCreate = array_map(fn (array $item, int $idx): array => [
                'no_id' => $item['no_id'] ?? ($idx + 1),
                'rambu_k3' => $item['rambu_k3'],
                'lokasi' => $item['lokasi'],
                'tingkat_pelanggaran' => $item['tingkat_pelanggaran'] ?? 'Nihil',
                'keterangan' => $item['keterangan'] ?? 'Perhatian',
                'sort_order' => $item['sort_order'] ?? $idx,
            ], $validated['items'], array_keys($validated['items']));

            $record->items()->createMany($itemsToCreate);

            $this->activityLogger->log(
                ActivityEvent::Created,
                "Menyimpan inspeksi rambu-rambu K3 ({$record->no_dokumen}) unit {$unit->name} periode {$validated['month']}/{$validated['year']} dengan ".count($itemsToCreate).' item rambu.',
                $record,
            );
        });

        return redirect()->back()->with('success', 'Data inspeksi rambu-rambu K3 berhasil disimpan.');
    }
}
