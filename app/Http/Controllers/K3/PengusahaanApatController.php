<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanApat;
use App\Models\K3PengusahaanApatItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanApatController extends Controller
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

        $record = K3PengusahaanApat::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record) {
            $itemsData = $record->items->map(fn (K3PengusahaanApatItem $item, int $idx): array => [
                'id' => $item->id,
                'no_urut' => $item->no_urut ?: ($idx + 1),
                'nama_alat' => $item->nama_alat,
                'tanggal_inspeksi' => $item->tanggal_inspeksi ?? '',
                'kondisi' => $item->kondisi ?? 'Baik',
                'jumlah' => $item->jumlah ?? 0,
                'keterangan' => $item->keterangan ?? '',
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $noDokumen = $record->no_dokumen;
            $revisi = $record->revisi;
            $tanggalDokumen = $record->tanggal_dokumen;
            $tanggalInspeksi = $record->tanggal_inspeksi ?? '';
            $catatan = $record->catatan ?? '';
        } else {
            // Check if there is an earlier record for this unit to prefill
            $previous = K3PengusahaanApat::query()
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

            $defaultDate = "12/{$month}/{$year}";

            if ($previous && $previous->items->isNotEmpty()) {
                $itemsData = $previous->items->map(fn (K3PengusahaanApatItem $item, int $idx): array => [
                    'id' => null,
                    'no_urut' => $item->no_urut ?: ($idx + 1),
                    'nama_alat' => $item->nama_alat,
                    'tanggal_inspeksi' => $defaultDate,
                    'kondisi' => $item->kondisi ?? 'Baik',
                    'jumlah' => $item->jumlah ?? 0,
                    'keterangan' => $item->keterangan ?? '',
                    'sort_order' => $item->sort_order ?? $idx,
                ])->all();

                $noDokumen = $previous->no_dokumen;
                $revisi = $previous->revisi;
                $tanggalDokumen = $previous->tanggal_dokumen;
                $tanggalInspeksi = $defaultDate;
                $catatan = '';
            } else {
                $itemsData = K3PengusahaanApat::buildDefaultRows($defaultDate);
                $noDokumen = 'SMT-FM-AK3-12.04';
                $revisi = '01';
                $tanggalDokumen = '23 September 2019';
                $tanggalInspeksi = $defaultDate;
                $catatan = '';
            }
        }

        return Inertia::render('pengusahaan/k3/apat/index', [
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
            'items.*.no_urut' => ['required', 'integer', 'min:1'],
            'items.*.nama_alat' => ['required', 'string', 'max:255'],
            'items.*.tanggal_inspeksi' => ['nullable', 'string', 'max:100'],
            'items.*.kondisi' => ['required', 'string', 'max:100'],
            'items.*.jumlah' => ['nullable', 'integer', 'min:0'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        $record = DB::transaction(function () use ($validated, $user): K3PengusahaanApat {
            $record = K3PengusahaanApat::query()->updateOrCreate(
                [
                    'unit_id' => $validated['unit_id'],
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'no_dokumen' => $validated['no_dokumen'] ?? 'SMT-FM-AK3-12.04',
                    'revisi' => $validated['revisi'] ?? '01',
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? '23 September 2019',
                    'tanggal_inspeksi' => $validated['tanggal_inspeksi'] ?? null,
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ],
            );

            $record->items()->delete();

            $itemsToInsert = [];
            $now = now();
            foreach ($validated['items'] as $index => $item) {
                $itemsToInsert[] = [
                    'laporan_id' => $record->id,
                    'no_urut' => (int) ($item['no_urut'] ?? ($index + 1)),
                    'nama_alat' => trim((string) $item['nama_alat']),
                    'tanggal_inspeksi' => ! empty($item['tanggal_inspeksi']) ? trim((string) $item['tanggal_inspeksi']) : ($validated['tanggal_inspeksi'] ?? null),
                    'kondisi' => trim((string) ($item['kondisi'] ?? 'Baik')),
                    'jumlah' => isset($item['jumlah']) && $item['jumlah'] !== '' ? (int) $item['jumlah'] : 0,
                    'keterangan' => ! empty($item['keterangan']) ? trim((string) $item['keterangan']) : null,
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($itemsToInsert)) {
                K3PengusahaanApatItem::query()->insert($itemsToInsert);
            }

            return $record;
        });

        $this->activityLogger->log(
            event: ActivityEvent::Created,
            description: "Menyimpan data kondisi alat pemadam api tradisional (APAT) bulan {$validated['month']} tahun {$validated['year']} untuk unit {$unit->name}",
            subject: $record,
            properties: [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'month' => $validated['month'],
                'year' => $validated['year'],
                'items_count' => count($validated['items']),
            ],
            unit: $unit,
        );

        return redirect()->back()->with('success', 'Data kondisi alat pemadam api tradisional berhasil disimpan.');
    }
}
