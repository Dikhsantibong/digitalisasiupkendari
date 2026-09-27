<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanApelKeamanan;
use App\Models\K3PengusahaanApelKeamananItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanApelKeamananController extends Controller
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

        $record = K3PengusahaanApelKeamanan::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record && $record->items->isNotEmpty()) {
            $itemsData = $record->items->map(fn (K3PengusahaanApelKeamananItem $item, int $idx): array => [
                'id' => $item->id,
                'tanggal' => $item->tanggal ? Carbon::parse($item->tanggal)->toDateString() : '',
                'hari_ke' => $item->hari_ke,
                'tim_regu' => $item->tim_regu,
                'shift' => $item->shift,
                'waktu_apel' => $item->waktu_apel,
                'jumlah_personil' => $item->jumlah_personil,
                'kelengkapan_atribut' => $item->kelengkapan_atribut ?? 'Lengkap',
                'paraf_komandan_regu' => $item->paraf_komandan_regu ?? '',
                'keterangan' => $item->keterangan ?? '',
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $noDokumen = $record->no_dokumen;
            $revisi = $record->revisi;
            $tanggalDokumen = $record->tanggal_dokumen;
            $catatan = $record->catatan ?? '';
        } else {
            $itemsData = K3PengusahaanApelKeamanan::buildDefaultRows($year, $month);
            $noDokumen = 'SMT-FM-AK3-13.13';
            $revisi = '00';
            $tanggalDokumen = '23 SEPTEMBER 2019';
            $catatan = '';
        }

        return Inertia::render('pengusahaan/k3/apel-keamanan/index', [
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
            'items.*.tanggal' => ['required', 'date'],
            'items.*.hari_ke' => ['required', 'integer', 'between:1,31'],
            'items.*.tim_regu' => ['required', 'string', 'max:50'],
            'items.*.shift' => ['required', 'string', 'max:50'],
            'items.*.waktu_apel' => ['required', 'string', 'max:50'],
            'items.*.jumlah_personil' => ['required', 'integer', 'min:0'],
            'items.*.kelengkapan_atribut' => ['required', 'string', 'max:100'],
            'items.*.paraf_komandan_regu' => ['nullable', 'string', 'max:100'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        $record = DB::transaction(function () use ($validated, $user): K3PengusahaanApelKeamanan {
            $record = K3PengusahaanApelKeamanan::query()->updateOrCreate(
                [
                    'unit_id' => $validated['unit_id'],
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'no_dokumen' => $validated['no_dokumen'] ?? 'SMT-FM-AK3-13.13',
                    'revisi' => $validated['revisi'] ?? '00',
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? '23 SEPTEMBER 2019',
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
                    'tanggal' => Carbon::parse($item['tanggal'])->toDateString(),
                    'hari_ke' => (int) $item['hari_ke'],
                    'tim_regu' => trim((string) $item['tim_regu']),
                    'shift' => trim((string) $item['shift']),
                    'waktu_apel' => trim((string) $item['waktu_apel']),
                    'jumlah_personil' => (int) ($item['jumlah_personil'] ?? 2),
                    'kelengkapan_atribut' => trim((string) ($item['kelengkapan_atribut'] ?? 'Lengkap')),
                    'paraf_komandan_regu' => ! empty($item['paraf_komandan_regu']) ? trim((string) $item['paraf_komandan_regu']) : null,
                    'keterangan' => ! empty($item['keterangan']) ? trim((string) $item['keterangan']) : null,
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($itemsToInsert)) {
                K3PengusahaanApelKeamananItem::query()->insert($itemsToInsert);
            }

            return $record;
        });

        $this->activityLogger->log(
            event: ActivityEvent::Created,
            description: "Menyimpan laporan apel satuan keamanan bulan {$validated['month']} tahun {$validated['year']} untuk unit {$unit->name}",
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

        return redirect()->back()->with('success', 'Laporan apel satuan keamanan berhasil disimpan.');
    }
}
