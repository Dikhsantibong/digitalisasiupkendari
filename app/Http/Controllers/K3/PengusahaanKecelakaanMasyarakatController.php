<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanKecelakaanMasyarakat;
use App\Models\K3PengusahaanKecelakaanMasyarakatItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanKecelakaanMasyarakatController extends Controller
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

        $record = K3PengusahaanKecelakaanMasyarakat::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        $recordData = [
            'is_nihil' => $record ? $record->is_nihil : true,
            'lampiran_teks' => $record?->lampiran_teks ?? 'Lampiran 1 Keputusan Direksi PT PLN (Persero)',
            'nomor_keputusan' => $record?->nomor_keputusan ?? 'Nomor :0252.P/DIR/2016',
            'catatan' => $record?->catatan ?? '',
            'items' => $record
                ? $record->items->map(fn (K3PengusahaanKecelakaanMasyarakatItem $item): array => [
                    'id' => $item->id,
                    'no_urut' => $item->no_urut,
                    'tanggal_kejadian' => $item->tanggal_kejadian ?? '',
                    'fungsi' => $item->fungsi ?? '',
                    'lokasi_kejadian' => $item->lokasi_kejadian ?? '',
                    'luka_ringan' => $item->luka_ringan,
                    'luka_berat' => $item->luka_berat,
                    'meninggal' => $item->meninggal,
                    'kerugian_material' => (float) $item->kerugian_material,
                    'keterangan' => $item->keterangan ?? '',
                ])->all()
                : [],
        ];

        return Inertia::render('k3/pengusahaan/kecelakaan-masyarakat/index', [
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
            'record' => $recordData,
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
            'year' => ['required', 'integer', 'between:2000,2100'],
            'is_nihil' => ['required', 'boolean'],
            'lampiran_teks' => ['nullable', 'string', 'max:255'],
            'nomor_keputusan' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:3000'],
            'items' => ['nullable', 'array'],
            'items.*.tanggal_kejadian' => ['nullable', 'string', 'max:50'],
            'items.*.fungsi' => ['nullable', 'string', 'max:100'],
            'items.*.lokasi_kejadian' => ['nullable', 'string', 'max:255'],
            'items.*.luka_ringan' => ['nullable', 'integer', 'min:0'],
            'items.*.luka_berat' => ['nullable', 'integer', 'min:0'],
            'items.*.meninggal' => ['nullable', 'integer', 'min:0'],
            'items.*.kerugian_material' => ['nullable', 'numeric', 'min:0'],
            'items.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $isNihil = (bool) $validated['is_nihil'];

        DB::transaction(function () use ($unit, $year, $month, $isNihil, $validated, $user): void {
            /** @var K3PengusahaanKecelakaanMasyarakat $laporan */
            $laporan = K3PengusahaanKecelakaanMasyarakat::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'is_nihil' => $isNihil,
                    'lampiran_teks' => $validated['lampiran_teks'] ?? 'Lampiran 1 Keputusan Direksi PT PLN (Persero)',
                    'nomor_keputusan' => $validated['nomor_keputusan'] ?? 'Nomor :0252.P/DIR/2016',
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );

            // Always clear old items
            $laporan->items()->delete();

            if (! $isNihil && ! empty($validated['items'])) {
                $itemsToInsert = [];
                $now = Carbon::now();
                foreach ($validated['items'] as $index => $item) {
                    $itemsToInsert[] = [
                        'laporan_id' => $laporan->id,
                        'no_urut' => $index + 1,
                        'tanggal_kejadian' => $item['tanggal_kejadian'] ?? null,
                        'fungsi' => $item['fungsi'] ?? null,
                        'lokasi_kejadian' => $item['lokasi_kejadian'] ?? null,
                        'luka_ringan' => (int) ($item['luka_ringan'] ?? 0),
                        'luka_berat' => (int) ($item['luka_berat'] ?? 0),
                        'meninggal' => (int) ($item['meninggal'] ?? 0),
                        'kerugian_material' => (float) ($item['kerugian_material'] ?? 0),
                        'keterangan' => $item['keterangan'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($itemsToInsert)) {
                    K3PengusahaanKecelakaanMasyarakatItem::query()->insert($itemsToInsert);
                }
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Laporan Bulanan Kecelakaan Masyarakat Umum Pengusahaan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Laporan Bulanan Kecelakaan Masyarakat Umum berhasil disimpan.',
        ]);

        return back();
    }
}
