<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanInspeksiTempatKerja;
use App\Models\K3PengusahaanInspeksiTempatKerjaItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanInspeksiTempatKerjaController extends Controller
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

        $record = K3PengusahaanInspeksiTempatKerja::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record && $record->items->isNotEmpty()) {
            $itemsData = $record->items->map(fn (K3PengusahaanInspeksiTempatKerjaItem $item, int $idx): array => [
                'id' => $item->id,
                'category' => $item->category,
                'no_urut' => $item->no_urut ?? ($idx + 1),
                'item' => $item->item,
                'status' => $item->status,
                'comment' => $item->comment ?? '',
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $noDokumen = $record->no_dokumen;
            $revisi = $record->revisi;
            $tanggalDokumen = $record->tanggal_dokumen;
            $tanggalInspeksi = $record->tanggal_inspeksi ?? '';
            $departemen = $record->departemen ?? 'PLN NUSANTARA POWER';
            $lokasi = $record->lokasi ?? '';
            $timInspektur = $record->tim_inspektur ?? '';
            $ketuaTim = $record->ketua_tim ?? '';
            $inspektur = $record->inspektur ?? '';
            $catatan = $record->catatan ?? '';
        } else {
            $itemsData = K3PengusahaanInspeksiTempatKerja::buildDefaultRows();
            $noDokumen = 'SMT-FM-AK3-12-01';
            $revisi = '01';
            $tanggalDokumen = '23 September 2019';
            $tanggalInspeksi = '';
            $departemen = 'PLN NUSANTARA POWER';
            $lokasi = '';
            $timInspektur = '';
            $ketuaTim = '';
            $inspektur = '';
            $catatan = '';
        }

        return Inertia::render('pengusahaan/k3/inspeksi-tempat-kerja/index', [
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
                'departemen' => $departemen,
                'lokasi' => $lokasi,
                'tim_inspektur' => $timInspektur,
                'ketua_tim' => $ketuaTim,
                'inspektur' => $inspektur,
                'catatan' => $catatan,
                'items' => $itemsData,
            ],
            'sample_items' => K3PengusahaanInspeksiTempatKerja::buildSampleRows(),
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
            'departemen' => ['nullable', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'tim_inspektur' => ['nullable', 'string', 'max:255'],
            'ketua_tim' => ['nullable', 'string', 'max:255'],
            'inspektur' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.category' => ['required', 'string'],
            'items.*.no_urut' => ['required', 'integer'],
            'items.*.item' => ['required', 'string'],
            'items.*.status' => ['nullable', 'string', 'in:Y,N,NA'],
            'items.*.comment' => ['nullable', 'string'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $unit, $user): void {
            $record = K3PengusahaanInspeksiTempatKerja::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'no_dokumen' => $validated['no_dokumen'] ?? 'SMT-FM-AK3-12-01',
                    'revisi' => $validated['revisi'] ?? '01',
                    'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? '23 September 2019',
                    'tanggal_inspeksi' => $validated['tanggal_inspeksi'] ?? null,
                    'departemen' => $validated['departemen'] ?? 'PLN NUSANTARA POWER',
                    'lokasi' => $validated['lokasi'] ?? null,
                    'tim_inspektur' => $validated['tim_inspektur'] ?? null,
                    'ketua_tim' => $validated['ketua_tim'] ?? null,
                    'inspektur' => $validated['inspektur'] ?? null,
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );

            $record->items()->delete();

            $itemsToCreate = array_map(fn (array $item, int $idx): array => [
                'category' => $item['category'],
                'no_urut' => $item['no_urut'] ?? ($idx + 1),
                'item' => $item['item'],
                'status' => $item['status'] ?? null,
                'comment' => $item['comment'] ?? null,
                'sort_order' => $item['sort_order'] ?? $idx,
            ], $validated['items'], array_keys($validated['items']));

            $record->items()->createMany($itemsToCreate);

            $this->activityLogger->log(
                ActivityEvent::Created,
                "Menyimpan formulir checklist inspeksi tempat kerja dan fasilitas ({$record->no_dokumen}) unit {$unit->name} periode {$validated['month']}/{$validated['year']} dengan ".count($itemsToCreate).' item.',
                $record,
            );
        });

        return redirect()->back()->with('success', 'Data formulir inspeksi tempat kerja dan fasilitas berhasil disimpan.');
    }
}
