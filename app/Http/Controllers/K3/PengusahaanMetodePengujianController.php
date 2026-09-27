<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanMetodePengujian;
use App\Models\K3PengusahaanMetodePengujianMeta;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanMetodePengujianController extends Controller
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

        [$rows, $hasSaved] = $this->loadRows($unit, $month, $year);
        $meta = $this->findMeta($unit, $month, $year);

        return Inertia::render('pengusahaan/k3/metode-pengujian/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
                'answers' => ['-', 'Memenuhi', 'Belum Memenuhi', 'Tidak Memenuhi', 'N/A'],
            ],
            'rows' => $rows,
            'meta' => [
                'nomor_dokumen' => $meta?->nomor_dokumen ?? '',
                'tanggal_terbit' => $meta?->tanggal_terbit ?? '',
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
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array'],
            'rows.*.no_urut' => ['nullable', 'string', 'max:50'],
            'rows.*.nama_peralatan' => ['required', 'string', 'max:255'],
            'rows.*.no_pengesahan' => ['nullable', 'string', 'max:255'],
            'rows.*.nama_kategori_alat' => ['nullable', 'string', 'max:255'],
            'rows.*.uji_visual' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_fungsi' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_beban' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_hydro' => ['nullable', 'string', 'max:100'],
            'rows.*.ndt' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_ultrasonic_thickness' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_ketahanan' => ['nullable', 'string', 'max:100'],
            'rows.*.sertifikasi_terakhir' => ['nullable', 'string', 'max:100'],
            'rows.*.sertifikasi_ulang' => ['nullable', 'string', 'max:100'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:2000'],
            'rows.*.sort_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'meta.nomor_dokumen' => ['nullable', 'string', 'max:100'],
            'meta.tanggal_terbit' => ['nullable', 'string', 'max:100'],
            'meta.revisi' => ['nullable', 'string', 'max:50'],
            'meta.halaman' => ['nullable', 'string', 'max:50'],
            'meta.catatan' => ['nullable', 'string', 'max:3000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($unit, $month, $year, $validated, $user): void {
            K3PengusahaanMetodePengujian::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach ($validated['rows'] as $index => $row) {
                K3PengusahaanMetodePengujian::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $row['no_urut'] ?? (string) ($index + 1),
                    'nama_peralatan' => $row['nama_peralatan'],
                    'no_pengesahan' => $row['no_pengesahan'] ?? '-',
                    'nama_kategori_alat' => $row['nama_kategori_alat'] ?? '-',
                    'uji_visual' => $row['uji_visual'] ?? '-',
                    'uji_fungsi' => $row['uji_fungsi'] ?? '-',
                    'uji_beban' => $row['uji_beban'] ?? '-',
                    'uji_hydro' => $row['uji_hydro'] ?? '-',
                    'ndt' => $row['ndt'] ?? '-',
                    'uji_ultrasonic_thickness' => $row['uji_ultrasonic_thickness'] ?? '-',
                    'uji_ketahanan' => $row['uji_ketahanan'] ?? '-',
                    'sertifikasi_terakhir' => $row['sertifikasi_terakhir'] ?? '-',
                    'sertifikasi_ulang' => $row['sertifikasi_ulang'] ?? '-',
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'input_by' => $user->id,
                ]);
            }

            if (isset($validated['meta'])) {
                K3PengusahaanMetodePengujianMeta::query()->updateOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'nomor_dokumen' => $validated['meta']['nomor_dokumen'] ?? null,
                        'tanggal_terbit' => $validated['meta']['tanggal_terbit'] ?? null,
                        'revisi' => $validated['meta']['revisi'] ?? null,
                        'halaman' => $validated['meta']['halaman'] ?? null,
                        'catatan' => $validated['meta']['catatan'] ?? null,
                    ]
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Formulir Metode Pengujian Peralatan Pengusahaan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir Metode Pengujian Peralatan Pengusahaan berhasil disimpan.',
        ]);

        return back();
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function loadRows(Unit $unit, int $month, int $year): array
    {
        $saved = K3PengusahaanMetodePengujian::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($saved->isEmpty()) {
            return [$this->defaultRows(), false];
        }

        return [$saved->map(fn (K3PengusahaanMetodePengujian $r, int $idx): array => [
            'id' => $r->id,
            'no_urut' => $r->no_urut ?? (string) ($idx + 1),
            'nama_peralatan' => $r->nama_peralatan,
            'no_pengesahan' => $r->no_pengesahan ?? '-',
            'nama_kategori_alat' => $r->nama_kategori_alat ?? '-',
            'uji_visual' => $r->uji_visual ?? '-',
            'uji_fungsi' => $r->uji_fungsi ?? '-',
            'uji_beban' => $r->uji_beban ?? '-',
            'uji_hydro' => $r->uji_hydro ?? '-',
            'ndt' => $r->ndt ?? '-',
            'uji_ultrasonic_thickness' => $r->uji_ultrasonic_thickness ?? '-',
            'uji_ketahanan' => $r->uji_ketahanan ?? '-',
            'sertifikasi_terakhir' => $r->sertifikasi_terakhir ?? '-',
            'sertifikasi_ulang' => $r->sertifikasi_ulang ?? '-',
            'keterangan' => $r->keterangan ?? '',
            'sort_order' => $r->sort_order,
        ])->all(), true];
    }

    private function findMeta(Unit $unit, int $month, int $year): ?K3PengusahaanMetodePengujianMeta
    {
        return K3PengusahaanMetodePengujianMeta::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * @return list<array{year: int, month: int, items: int, updated_at: string|null}>
     */
    private function history(Unit $unit): array
    {
        return K3PengusahaanMetodePengujian::query()
            ->where('unit_id', $unit->id)
            ->selectRaw('year, month, COUNT(*) as items, MAX(updated_at) as last_updated')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(24)
            ->get()
            ->map(fn (K3PengusahaanMetodePengujian $row): array => [
                'year' => (int) $row->year,
                'month' => (int) $row->month,
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
                'nama_peralatan' => 'Crane',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Overhead Travelling Crane',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => '-',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 0,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_peralatan' => 'Tangki Timbun',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki #1 Containerized',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Belum Memenuhi',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 1,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_peralatan' => 'Tangki Timbun',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki #1 HSD',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Belum Memenuhi',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 2,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_peralatan' => 'Tangki Timbun',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki #2 HSD',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Belum Memenuhi',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 3,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_peralatan' => 'Tangki Timbun',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki #1 MFO',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Belum Memenuhi',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 4,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_peralatan' => 'Tangki Timbun',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki #2 MFO',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Belum Memenuhi',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 5,
            ],
            [
                'id' => null,
                'no_urut' => '2',
                'nama_peralatan' => 'Tangki Timbun',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki #3 MFO',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Belum Memenuhi',
                'uji_ketahanan' => '-',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 6,
            ],
            [
                'id' => null,
                'no_urut' => '3',
                'nama_peralatan' => 'Instalasi Penyalur Petir',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Power House',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => '-',
                'uji_hydro' => '-',
                'ndt' => '-',
                'uji_ultrasonic_thickness' => '-',
                'uji_ketahanan' => 'Belum Memenuhi',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 7,
            ],
            [
                'id' => null,
                'no_urut' => '3',
                'nama_peralatan' => 'Instalasi Penyalur Petir',
                'no_pengesahan' => '-',
                'nama_kategori_alat' => 'Tangki HSD 02',
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => '-',
                'uji_beban' => '-',
                'uji_hydro' => '-',
                'ndt' => '-',
                'uji_ultrasonic_thickness' => '-',
                'uji_ketahanan' => 'Belum Memenuhi',
                'sertifikasi_terakhir' => '-',
                'sertifikasi_ulang' => '-',
                'keterangan' => '',
                'sort_order' => 8,
            ],
        ];
    }
}
