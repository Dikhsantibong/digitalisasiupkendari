<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanJamKerja;
use App\Models\K3PengusahaanJamKerjaBulanan;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanJamKerjaBulananController extends Controller
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

        $record = K3PengusahaanJamKerjaBulanan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $detailRecord = K3PengusahaanJamKerja::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $prevDate = Carbon::createFromDate($year, $month, 1)->subMonth();
        $prevRecord = K3PengusahaanJamKerjaBulanan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $prevDate->year)
            ->where('month', $prevDate->month)
            ->first();
        $komulatifLalu = $prevRecord ? (float) $prevRecord->jam_kerja_komulatif_bulan_ini : null;

        $history = K3PengusahaanJamKerjaBulanan::query()
            ->where('unit_id', $unit->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get(['year', 'month', 'jam_kerja_komulatif_bulan_ini', 'updated_at'])
            ->map(fn (K3PengusahaanJamKerjaBulanan $r): array => [
                'year' => (int) $r->year,
                'month' => (int) $r->month,
                'jam_kerja_komulatif_bulan_ini' => (float) $r->jam_kerja_komulatif_bulan_ini,
                'updated_at' => $r->updated_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('pengusahaan/k3/jam-kerja-bulanan/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'record' => $record ? $record->toArray() : $this->buildDefaultData($unit, $year, $month, $detailRecord, $komulatifLalu),
            'has_saved' => $record !== null,
            'has_detail_record' => $detailRecord !== null,
            'komulatif_lalu_suggested' => $komulatifLalu,
            'history' => $history,
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
            'no_dokumen' => ['nullable', 'string', 'max:100'],
            'tgl_berlaku' => ['nullable', 'string', 'max:100'],
            'revisi' => ['nullable', 'string', 'max:50'],
            'halaman' => ['nullable', 'string', 'max:50'],
            'jam_kerja_komulatif_bulan_lalu' => ['nullable', 'numeric', 'min:0'],
            'karyawan_tetap' => ['nullable', 'integer', 'min:0'],
            'karyawan_tetap_shift' => ['nullable', 'integer', 'min:0'],
            'karyawan_tidak_tetap' => ['nullable', 'integer', 'min:0'],
            'karyawan_tidak_tetap_shift' => ['nullable', 'integer', 'min:0'],
            'hari_kerja' => ['nullable', 'integer', 'min:0'],
            'jam_kerja_standart_karyawan' => ['nullable', 'numeric', 'min:0'],
            'jam_kerja_lembur_karyawan' => ['nullable', 'numeric', 'min:0'],
            'jam_absensi_karyawan' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:3000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $computed = K3PengusahaanJamKerjaBulanan::computeRows($validated);

        DB::transaction(function () use ($unit, $year, $month, $validated, $computed, $user): void {
            K3PengusahaanJamKerjaBulanan::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                ],
                array_merge($validated, $computed, [
                    'input_by' => $user->id,
                ])
            );
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Laporan Bulanan Jumlah Jam Kerja Karyawan Pengusahaan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Laporan Bulanan Jumlah Jam Kerja Karyawan berhasil disimpan.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultData(
        Unit $unit,
        int $year,
        int $month,
        ?K3PengusahaanJamKerja $detailRecord,
        ?float $komulatifLalu
    ): array {
        if ($detailRecord) {
            $data = [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_dokumen' => 'FMZ-08.4.4.11',
                'tgl_berlaku' => '15 Okt 2014',
                'revisi' => '0.0',
                'halaman' => '1 dari 1',
                'jam_kerja_komulatif_bulan_lalu' => $komulatifLalu ?? 10020.0,
                'karyawan_tetap' => $detailRecord->karyawan_tetap,
                'karyawan_tetap_shift' => $detailRecord->karyawan_tetap_shift,
                'karyawan_tidak_tetap' => $detailRecord->karyawan_tidak_tetap,
                'karyawan_tidak_tetap_shift' => $detailRecord->karyawan_tidak_tetap_shift,
                'hari_kerja' => $detailRecord->hari_tetap ?: 22,
                'jam_kerja_standart_karyawan' => $detailRecord->total_jam_kerja_orang,
                'jam_kerja_lembur_karyawan' => $detailRecord->total_lembur,
                'jam_absensi_karyawan' => $detailRecord->total_absensi_jam,
                'catatan' => '',
            ];
        } else {
            $data = [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_dokumen' => 'FMZ-08.4.4.11',
                'tgl_berlaku' => '15 Okt 2014',
                'revisi' => '0.0',
                'halaman' => '1 dari 1',
                'jam_kerja_komulatif_bulan_lalu' => $komulatifLalu ?? 10020.0,
                'karyawan_tetap' => 7,
                'karyawan_tetap_shift' => 0,
                'karyawan_tidak_tetap' => 29,
                'karyawan_tidak_tetap_shift' => 12,
                'hari_kerja' => 22,
                'jam_kerja_standart_karyawan' => 9312.0,
                'jam_kerja_lembur_karyawan' => 732.0,
                'jam_absensi_karyawan' => 24.0,
                'catatan' => '',
            ];
        }

        return array_merge($data, K3PengusahaanJamKerjaBulanan::computeRows($data));
    }
}
