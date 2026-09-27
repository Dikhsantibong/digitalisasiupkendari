<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanPemeriksaanP3k;
use App\Models\K3PengusahaanPemeriksaanP3kItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanPemeriksaanP3kController extends Controller
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

        $record = K3PengusahaanPemeriksaanP3k::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record) {
            $locations = ! empty($record->locations) ? $record->locations : K3PengusahaanPemeriksaanP3k::DEFAULT_LOCATIONS;
            $itemsData = $record->items->map(fn (K3PengusahaanPemeriksaanP3kItem $item, int $idx): array => [
                'id' => $item->id,
                'no_urut' => $item->no_urut ?: ($idx + 1),
                'nama_isi' => $item->nama_isi,
                'standar_jumlah' => $item->standar_jumlah,
                'satuan' => $item->satuan,
                'kondisi_lokasi' => $item->kondisi_lokasi ?: [],
                'keterangan' => $item->keterangan ?? '',
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $noDokumen = $record->no_dokumen;
            $revisi = $record->revisi;
            $tanggalDokumen = $record->tanggal_dokumen;
            $catatan = $record->catatan ?? '';
        } else {
            // Check if there is any earlier record for this unit to prefill
            $previous = K3PengusahaanPemeriksaanP3k::query()
                ->with('items')
                ->where('unit_id', $unit->id)
                ->where(function ($q) use ($year, $month) {
                    $q->where('year', '<', $year)
                        ->orWhere(function ($q2) use ($year, $month) {
                            $q2->where('year', $year)
                                ->where('month', '<', $month);
                        });
                })
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();

            if ($previous && $previous->items->isNotEmpty()) {
                $locations = ! empty($previous->locations) ? $previous->locations : K3PengusahaanPemeriksaanP3k::DEFAULT_LOCATIONS;
                $itemsData = $previous->items->map(fn (K3PengusahaanPemeriksaanP3kItem $item, int $idx): array => [
                    'id' => null,
                    'no_urut' => $item->no_urut ?: ($idx + 1),
                    'nama_isi' => $item->nama_isi,
                    'standar_jumlah' => $item->standar_jumlah,
                    'satuan' => $item->satuan,
                    'kondisi_lokasi' => $item->kondisi_lokasi ?: [],
                    'keterangan' => $item->keterangan ?? '',
                    'sort_order' => $item->sort_order ?? $idx,
                ])->all();

                $noDokumen = $previous->no_dokumen;
                $revisi = $previous->revisi;
                $tanggalDokumen = $previous->tanggal_dokumen;
                $catatan = '';
            } else {
                $locations = K3PengusahaanPemeriksaanP3k::DEFAULT_LOCATIONS;
                $itemsData = K3PengusahaanPemeriksaanP3k::buildDefaultRows($locations);
                $noDokumen = 'SMT-FM-AK3-10.01';
                $revisi = '01';
                $tanggalDokumen = '23 September 2019';
                $catatan = '';
            }
        }

        return Inertia::render('k3/pengusahaan/pemeriksaan-p3k/index', [
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
                'locations' => $locations,
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
            'locations' => ['required', 'array', 'min:1'],
            'locations.*' => ['required', 'string', 'max:100'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.no_urut' => ['required', 'integer', 'min:1'],
            'items.*.nama_isi' => ['required', 'string', 'max:255'],
            'items.*.standar_jumlah' => ['required', 'integer', 'min:0'],
            'items.*.satuan' => ['required', 'string', 'max:50'],
            'items.*.kondisi_lokasi' => ['nullable', 'array'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        $record = DB::transaction(function () use ($validated, $user): K3PengusahaanPemeriksaanP3k {
            $record = K3PengusahaanPemeriksaanP3k::query()->updateOrCreate(
                [
                    'unit_id' => $validated['unit_id'],
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'no_dokumen' => $validated['no_dokumen'] ?? 'SMT-FM-AK3-10.01',
                    'revisi' => $validated['revisi'] ?? '01',
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? '23 September 2019',
                    'locations' => array_values(array_filter(array_map('trim', $validated['locations']))),
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ],
            );

            // Recreate items cleanly
            $record->items()->delete();

            $itemsToInsert = [];
            $now = now();
            foreach ($validated['items'] as $index => $item) {
                $itemsToInsert[] = [
                    'laporan_id' => $record->id,
                    'no_urut' => (int) ($item['no_urut'] ?? ($index + 1)),
                    'nama_isi' => trim((string) $item['nama_isi']),
                    'standar_jumlah' => (int) ($item['standar_jumlah'] ?? 1),
                    'satuan' => trim((string) ($item['satuan'] ?? 'buah')),
                    'kondisi_lokasi' => ! empty($item['kondisi_lokasi']) ? json_encode($item['kondisi_lokasi']) : json_encode([]),
                    'keterangan' => ! empty($item['keterangan']) ? trim((string) $item['keterangan']) : null,
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($itemsToInsert)) {
                K3PengusahaanPemeriksaanP3kItem::query()->insert($itemsToInsert);
            }

            return $record;
        });

        $this->activityLogger->log(
            event: ActivityEvent::Created,
            description: "Menyimpan pemeriksaan peralatan P3K bulan {$validated['month']} tahun {$validated['year']} untuk unit {$unit->name}",
            subject: $record,
            properties: [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'month' => $validated['month'],
                'year' => $validated['year'],
                'items_count' => count($validated['items']),
                'locations_count' => count($validated['locations']),
            ],
            unit: $unit,
        );

        return redirect()->back()->with('success', 'Pemeriksaan peralatan P3K berhasil disimpan.');
    }
}
