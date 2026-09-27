<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanEvaluasiPengujian;
use App\Models\K3PengusahaanEvaluasiPengujianMeta;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanEvaluasiPengujianController extends Controller
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
        $year = (int) ($request->integer('year') ?: $now->year);

        [$rows, $hasSaved] = $this->loadRows($unit, $year);
        $meta = $this->findMeta($unit, $year);

        return Inertia::render('pengusahaan/k3/evaluasi-pengujian/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'year' => $year],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'rows' => $rows,
            'meta' => [
                'nomor_dokumen' => $meta?->nomor_dokumen ?? 'FMG-08-2.3.60',
                'tanggal_terbit' => $meta?->tanggal_terbit ?? '21 Mei 2018',
                'revisi' => $meta?->revisi ?? '',
                'halaman' => $meta?->halaman ?? '',
                'catatan' => $meta?->catatan ?? '',
            ],
            'has_saved' => $hasSaved,
            'history' => $this->history($unit),
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
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array'],
            'rows.*.no_urut' => ['nullable', 'string', 'max:50'],
            'rows.*.nama_kategori_alat' => ['required', 'string', 'max:255'],
            'rows.*.jenis' => ['required', 'string', 'max:255'],
            'rows.*.kapasitas' => ['nullable', 'string', 'max:255'],
            'rows.*.temuan_sertifikat' => ['nullable', 'string', 'max:3000'],
            'rows.*.progres_bulan' => ['nullable', 'array'],
            'rows.*.progres_bulan.*' => ['integer', 'between:1,12'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:2000'],
            'rows.*.sort_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'meta.nomor_dokumen' => ['nullable', 'string', 'max:100'],
            'meta.tanggal_terbit' => ['nullable', 'string', 'max:100'],
            'meta.revisi' => ['nullable', 'string', 'max:50'],
            'meta.halaman' => ['nullable', 'string', 'max:50'],
            'meta.catatan' => ['nullable', 'string', 'max:3000'],
        ]);

        $year = (int) $validated['year'];

        DB::transaction(function () use ($unit, $year, $validated, $user): void {
            K3PengusahaanEvaluasiPengujian::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->delete();

            foreach ($validated['rows'] as $index => $row) {
                K3PengusahaanEvaluasiPengujian::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'no_urut' => $row['no_urut'] ?? (string) ($index + 1),
                    'nama_kategori_alat' => $row['nama_kategori_alat'],
                    'jenis' => $row['jenis'],
                    'kapasitas' => $row['kapasitas'] ?? null,
                    'temuan_sertifikat' => $row['temuan_sertifikat'] ?? null,
                    'progres_bulan' => ! empty($row['progres_bulan']) ? array_values(array_unique($row['progres_bulan'])) : [],
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'input_by' => $user->id,
                ]);
            }

            if (isset($validated['meta'])) {
                K3PengusahaanEvaluasiPengujianMeta::query()->updateOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'year' => $year,
                    ],
                    [
                        'nomor_dokumen' => $validated['meta']['nomor_dokumen'] ?? 'FMG-08-2.3.60',
                        'tanggal_terbit' => $validated['meta']['tanggal_terbit'] ?? '21 Mei 2018',
                        'revisi' => $validated['meta']['revisi'] ?? null,
                        'halaman' => $validated['meta']['halaman'] ?? null,
                        'catatan' => $validated['meta']['catatan'] ?? null,
                    ]
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Formulir Evaluasi Hasil Pengujian Peralatan Pengusahaan {$unit->name} {$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir Evaluasi Hasil Pengujian Peralatan berhasil disimpan.',
        ]);

        return back();
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function loadRows(Unit $unit, int $year): array
    {
        $saved = K3PengusahaanEvaluasiPengujian::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($saved->isEmpty()) {
            return [$this->defaultRows(), false];
        }

        return [$saved->map(fn (K3PengusahaanEvaluasiPengujian $r, int $idx): array => [
            'id' => $r->id,
            'no_urut' => $r->no_urut ?? (string) ($idx + 1),
            'nama_kategori_alat' => $r->nama_kategori_alat,
            'jenis' => $r->jenis,
            'kapasitas' => $r->kapasitas ?? '',
            'temuan_sertifikat' => $r->temuan_sertifikat ?? '',
            'progres_bulan' => $r->progres_bulan ?? [],
            'keterangan' => $r->keterangan ?? '',
            'sort_order' => $r->sort_order,
        ])->all(), true];
    }

    private function findMeta(Unit $unit, int $year): ?K3PengusahaanEvaluasiPengujianMeta
    {
        return K3PengusahaanEvaluasiPengujianMeta::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->first();
    }

    /**
     * @return list<array{year: int, items: int, updated_at: string|null}>
     */
    private function history(Unit $unit): array
    {
        return K3PengusahaanEvaluasiPengujian::query()
            ->where('unit_id', $unit->id)
            ->selectRaw('year, COUNT(*) as items, MAX(updated_at) as last_updated')
            ->groupBy('year')
            ->orderByDesc('year')
            ->limit(10)
            ->get()
            ->map(fn (K3PengusahaanEvaluasiPengujian $row): array => [
                'year' => (int) $row->year,
                'items' => (int) $row->getAttribute('items'),
                'updated_at' => $row->getAttribute('last_updated'),
            ])
            ->all();
    }

    /**
     * Default standard rows matching the official PLN NP PLTD form.
     *
     * @return list<array<string, mixed>>
     */
    private function defaultRows(): array
    {
        return [
            [
                'id' => null,
                'no_urut' => '1',
                'nama_kategori_alat' => 'Crane',
                'jenis' => 'Overhead Traveling Crane',
                'kapasitas' => '5 ton',
                'temuan_sertifikat' => '',
                'progres_bulan' => [],
                'keterangan' => '',
                'sort_order' => 0,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_kategori_alat' => 'Tangki Timbun',
                'jenis' => 'Unit Containerized',
                'kapasitas' => '25.000 Liter',
                'temuan_sertifikat' => '',
                'progres_bulan' => [],
                'keterangan' => '',
                'sort_order' => 1,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_kategori_alat' => 'Tangki Timbun',
                'jenis' => 'Unit 1 HSD',
                'kapasitas' => '100.000 Liter',
                'temuan_sertifikat' => '',
                'progres_bulan' => [],
                'keterangan' => '',
                'sort_order' => 2,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_kategori_alat' => 'Tangki Timbun',
                'jenis' => 'Unit 2 HSD',
                'kapasitas' => '1500.000 Liter',
                'temuan_sertifikat' => '',
                'progres_bulan' => [],
                'keterangan' => '',
                'sort_order' => 3,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_kategori_alat' => 'Tangki Timbun',
                'jenis' => 'Unit 1 MFO',
                'kapasitas' => '1500.000 Liter',
                'temuan_sertifikat' => '',
                'progres_bulan' => [],
                'keterangan' => '',
                'sort_order' => 4,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_kategori_alat' => 'Tangki Timbun',
                'jenis' => 'Unit 2 MFO',
                'kapasitas' => '1500.000 Liter',
                'temuan_sertifikat' => '',
                'progres_bulan' => [],
                'keterangan' => '',
                'sort_order' => 5,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_kategori_alat' => 'Tangki Timbun',
                'jenis' => 'Unit 3 MFO',
                'kapasitas' => '1500.000 Liter',
                'temuan_sertifikat' => '',
                'progres_bulan' => [],
                'keterangan' => '',
                'sort_order' => 6,
            ],
            [
                'id' => null,
                'no_urut' => '3',
                'nama_kategori_alat' => 'Penyalur Petir',
                'jenis' => 'Power House',
                'kapasitas' => '-',
                'temuan_sertifikat' => '',
                'progres_bulan' => [1, 2, 3, 4, 5, 6, 7, 8],
                'keterangan' => 'Mei 2020 Close Temuan',
                'sort_order' => 7,
            ],
            [
                'id' => null,
                'no_urut' => '3',
                'nama_kategori_alat' => 'Penyalur Petir',
                'jenis' => 'Tangki HSD 02',
                'kapasitas' => '-',
                'temuan_sertifikat' => '',
                'progres_bulan' => [1, 2, 3, 4, 5, 6, 7, 8],
                'keterangan' => 'Mei 2020 Close Temuan',
                'sort_order' => 8,
            ],
        ];
    }
}
