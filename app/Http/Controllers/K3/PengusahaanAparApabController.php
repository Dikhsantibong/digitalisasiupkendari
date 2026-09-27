<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanAparApab;
use App\Models\K3PengusahaanAparApabItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanAparApabController extends Controller
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

        $record = K3PengusahaanAparApab::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record) {
            $itemsData = $record->items->map(fn (K3PengusahaanAparApabItem $item, int $idx): array => [
                'id' => $item->id,
                'no_urut' => $item->no_urut ?: ($idx + 1),
                'no_rfid' => $item->no_rfid ?? '',
                'lokasi' => $item->lokasi,
                'tgl_periksa' => $item->tgl_periksa ?? '',
                'merk_apar' => $item->merk_apar ?? '',
                'jenis_apar' => $item->jenis_apar ?? '',
                'berat_kg' => $item->berat_kg,
                'kondisi_tabung' => $item->kondisi_tabung ?? 'baik',
                'kondisi_nozzle_selang' => $item->kondisi_nozzle_selang ?? 'baik',
                'indikator_tekanan' => $item->indikator_tekanan ?? 'ok',
                'kondisi_pin_segel' => $item->kondisi_pin_segel ?? 'baik',
                'keterangan' => $item->keterangan ?? '',
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $noDokumen = $record->no_dokumen;
            $revisi = $record->revisi;
            $tanggalDokumen = $record->tanggal_dokumen;
            $catatan = $record->catatan ?? '';
        } else {
            // Check if there is an earlier record for this unit to prefill
            $previous = K3PengusahaanAparApab::query()
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
                $itemsData = $previous->items->map(fn (K3PengusahaanAparApabItem $item, int $idx): array => [
                    'id' => null,
                    'no_urut' => $item->no_urut ?: ($idx + 1),
                    'no_rfid' => $item->no_rfid ?? '',
                    'lokasi' => $item->lokasi,
                    'tgl_periksa' => '',
                    'merk_apar' => $item->merk_apar ?? '',
                    'jenis_apar' => $item->jenis_apar ?? '',
                    'berat_kg' => $item->berat_kg,
                    'kondisi_tabung' => 'baik',
                    'kondisi_nozzle_selang' => 'baik',
                    'indikator_tekanan' => 'ok',
                    'kondisi_pin_segel' => 'baik',
                    'keterangan' => $item->keterangan ?? '',
                    'sort_order' => $item->sort_order ?? $idx,
                ])->all();

                $noDokumen = $previous->no_dokumen;
                $revisi = $previous->revisi;
                $tanggalDokumen = $previous->tanggal_dokumen;
                $catatan = '';
            } else {
                $itemsData = K3PengusahaanAparApab::buildDefaultRows();
                $noDokumen = 'SMT-FM-AK3-12.03';
                $revisi = '01';
                $tanggalDokumen = '23 SEPTEMBER 2019';
                $catatan = '';
            }
        }

        return Inertia::render('pengusahaan/k3/apar-apab/index', [
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
            'items.*.no_urut' => ['required', 'integer', 'min:1'],
            'items.*.no_rfid' => ['nullable', 'string', 'max:100'],
            'items.*.lokasi' => ['required', 'string', 'max:255'],
            'items.*.tgl_periksa' => ['nullable', 'string', 'max:100'],
            'items.*.merk_apar' => ['nullable', 'string', 'max:100'],
            'items.*.jenis_apar' => ['nullable', 'string', 'max:100'],
            'items.*.berat_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.kondisi_tabung' => ['nullable', 'string', 'max:50'],
            'items.*.kondisi_nozzle_selang' => ['nullable', 'string', 'max:50'],
            'items.*.indikator_tekanan' => ['nullable', 'string', 'max:50'],
            'items.*.kondisi_pin_segel' => ['nullable', 'string', 'max:50'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        $record = DB::transaction(function () use ($validated, $user): K3PengusahaanAparApab {
            $record = K3PengusahaanAparApab::query()->updateOrCreate(
                [
                    'unit_id' => $validated['unit_id'],
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'no_dokumen' => $validated['no_dokumen'] ?? 'SMT-FM-AK3-12.03',
                    'revisi' => $validated['revisi'] ?? '01',
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? '23 SEPTEMBER 2019',
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
                    'no_rfid' => ! empty($item['no_rfid']) ? trim((string) $item['no_rfid']) : null,
                    'lokasi' => trim((string) $item['lokasi']),
                    'tgl_periksa' => ! empty($item['tgl_periksa']) ? trim((string) $item['tgl_periksa']) : null,
                    'merk_apar' => ! empty($item['merk_apar']) ? trim((string) $item['merk_apar']) : null,
                    'jenis_apar' => ! empty($item['jenis_apar']) ? trim((string) $item['jenis_apar']) : null,
                    'berat_kg' => isset($item['berat_kg']) && $item['berat_kg'] !== '' ? (float) $item['berat_kg'] : null,
                    'kondisi_tabung' => $item['kondisi_tabung'] ?? 'baik',
                    'kondisi_nozzle_selang' => $item['kondisi_nozzle_selang'] ?? 'baik',
                    'indikator_tekanan' => $item['indikator_tekanan'] ?? 'ok',
                    'kondisi_pin_segel' => $item['kondisi_pin_segel'] ?? 'baik',
                    'keterangan' => ! empty($item['keterangan']) ? trim((string) $item['keterangan']) : null,
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($itemsToInsert)) {
                K3PengusahaanAparApabItem::query()->insert($itemsToInsert);
            }

            return $record;
        });

        $this->activityLogger->log(
            event: ActivityEvent::Created,
            description: "Menyimpan data kondisi APAR dan APAB bulan {$validated['month']} tahun {$validated['year']} untuk unit {$unit->name}",
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

        return redirect()->back()->with('success', 'Data kondisi APAR dan APAB berhasil disimpan.');
    }
}
