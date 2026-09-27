<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanJamKerja;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanJamKerjaController extends Controller
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

        $record = K3PengusahaanJamKerja::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $history = K3PengusahaanJamKerja::query()
            ->where('unit_id', $unit->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get(['year', 'month', 'total_jam_kerja_seluruh', 'updated_at'])
            ->map(fn (K3PengusahaanJamKerja $r): array => [
                'year' => (int) $r->year,
                'month' => (int) $r->month,
                'total_jam_kerja_seluruh' => (float) $r->total_jam_kerja_seluruh,
                'updated_at' => $r->updated_at?->format('Y-m-d H:i'),
            ])
            ->all();

        return Inertia::render('k3/pengusahaan/jam-kerja/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'record' => $record ? $record->toArray() : $this->defaultData($unit, $year, $month),
            'has_saved' => $record !== null,
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
            'karyawan_tetap' => ['nullable', 'integer', 'min:0'],
            'karyawan_tetap_shift' => ['nullable', 'integer', 'min:0'],
            'karyawan_tidak_tetap' => ['nullable', 'integer', 'min:0'],
            'karyawan_tidak_tetap_shift' => ['nullable', 'integer', 'min:0'],
            'hari_tetap' => ['nullable', 'integer', 'min:0'],
            'jam_tetap' => ['nullable', 'numeric', 'min:0'],
            'lembur_tetap' => ['nullable', 'numeric', 'min:0'],
            'hari_tetap_shift' => ['nullable', 'integer', 'min:0'],
            'jam_tetap_shift' => ['nullable', 'numeric', 'min:0'],
            'lembur_tetap_shift' => ['nullable', 'numeric', 'min:0'],
            'hari_tidak_tetap' => ['nullable', 'integer', 'min:0'],
            'jam_tidak_tetap' => ['nullable', 'numeric', 'min:0'],
            'lembur_tidak_tetap' => ['nullable', 'numeric', 'min:0'],
            'hari_tidak_tetap_shift' => ['nullable', 'integer', 'min:0'],
            'jam_tidak_tetap_shift' => ['nullable', 'numeric', 'min:0'],
            'lembur_tidak_tetap_shift' => ['nullable', 'numeric', 'min:0'],
            'cuti_orang' => ['nullable', 'integer', 'min:0'],
            'cuti_hari' => ['nullable', 'integer', 'min:0'],
            'cuti_jam' => ['nullable', 'numeric', 'min:0'],
            'ijin_orang' => ['nullable', 'integer', 'min:0'],
            'ijin_hari' => ['nullable', 'integer', 'min:0'],
            'ijin_jam' => ['nullable', 'numeric', 'min:0'],
            'sakit_orang' => ['nullable', 'integer', 'min:0'],
            'sakit_hari' => ['nullable', 'integer', 'min:0'],
            'sakit_jam' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:3000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $totals = K3PengusahaanJamKerja::computeTotals($validated);

        DB::transaction(function () use ($unit, $year, $month, $validated, $totals, $user): void {
            K3PengusahaanJamKerja::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                ],
                array_merge($validated, $totals, [
                    'input_by' => $user->id,
                ])
            );
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Laporan Hari Kerja dan Jam Kerja Karyawan Pengusahaan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Laporan Hari Kerja dan Jam Kerja Karyawan berhasil disimpan.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultData(Unit $unit, int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $data = [
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_dokumen' => 'FMZ-08.4.4.10',
            'tgl_berlaku' => '15 Okt 2014',
            'revisi' => '0.0',
            'halaman' => '1 dari 1',
            'karyawan_tetap' => 7,
            'karyawan_tetap_shift' => 0,
            'karyawan_tidak_tetap' => 29,
            'karyawan_tidak_tetap_shift' => 12,
            'hari_tetap' => 22,
            'jam_tetap' => 8,
            'lembur_tetap' => 0,
            'hari_tetap_shift' => 0,
            'jam_tetap_shift' => 8,
            'lembur_tetap_shift' => 0,
            'hari_tidak_tetap' => 22,
            'jam_tidak_tetap' => 8,
            'lembur_tidak_tetap' => 0,
            'hari_tidak_tetap_shift' => $daysInMonth,
            'jam_tidak_tetap_shift' => 8,
            'lembur_tidak_tetap_shift' => 732,
            'cuti_orang' => 0,
            'cuti_hari' => 0,
            'cuti_jam' => 0,
            'ijin_orang' => 1,
            'ijin_hari' => 1,
            'ijin_jam' => 8,
            'sakit_orang' => 2,
            'sakit_hari' => 2,
            'sakit_jam' => 16,
            'catatan' => '',
        ];

        return array_merge($data, K3PengusahaanJamKerja::computeTotals($data));
    }
}
