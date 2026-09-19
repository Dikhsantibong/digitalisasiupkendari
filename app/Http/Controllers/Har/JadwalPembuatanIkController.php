<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HarJadwalPembuatanIk;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JadwalPembuatanIkController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);

        // Ambil personil unit untuk rekomendasi PIC Pembuat
        $employees = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $savedRecords = HarJadwalPembuatanIk::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            // Sediakan default 30 baris IK sesuai format dokumen resmi
            $rows = collect(range(1, 30))->map(function (int $num): array {
                return [
                    'id' => null,
                    'no_urut' => $num,
                    'instruksi_kerja' => 'IK......................................................',
                    'pic_pembuat' => '',
                    'rencana_bulan' => [],
                    'realisasi_bulan' => [],
                    'keterangan' => '',
                ];
            })->all();
        } else {
            $rows = $savedRecords->map(fn (HarJadwalPembuatanIk $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'instruksi_kerja' => $r->instruksi_kerja,
                'pic_pembuat' => $r->pic_pembuat ?? '',
                'rencana_bulan' => $r->rencana_bulan ?? [],
                'realisasi_bulan' => $r->realisasi_bulan ?? [],
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('har/jadwal/pembuatan-ik/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'year' => $year,
            ],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'employees' => $employees,
            'rows' => $rows,
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.instruksi_kerja' => ['required', 'string', 'max:500'],
            'rows.*.pic_pembuat' => ['nullable', 'string', 'max:255'],
            'rows.*.rencana_bulan' => ['nullable', 'array'],
            'rows.*.realisasi_bulan' => ['nullable', 'array'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $year): void {
            $existingIds = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $rencana = collect($row['rencana_bulan'] ?? [])
                    ->map(fn ($m) => (int) $m)
                    ->filter(fn ($m) => $m >= 1 && $m <= 12)
                    ->unique()
                    ->values()
                    ->all();

                $realisasi = collect($row['realisasi_bulan'] ?? [])
                    ->map(fn ($m) => (int) $m)
                    ->filter(fn ($m) => $m >= 1 && $m <= 12)
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($row['id'])) {
                    $record = HarJadwalPembuatanIk::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update([
                            'no_urut' => $row['no_urut'] ?? ($index + 1),
                            'instruksi_kerja' => $row['instruksi_kerja'],
                            'pic_pembuat' => $row['pic_pembuat'] ?? null,
                            'rencana_bulan' => $rencana,
                            'realisasi_bulan' => $realisasi,
                            'keterangan' => $row['keterangan'] ?? null,
                            'sort_order' => $index,
                            'input_by' => $user->id,
                        ]);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = HarJadwalPembuatanIk::create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'instruksi_kerja' => $row['instruksi_kerja'],
                    'pic_pembuat' => $row['pic_pembuat'] ?? null,
                    'rencana_bulan' => $rencana,
                    'realisasi_bulan' => $realisasi,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
                $existingIds[] = $newRecord->id;
            }

            if (! empty($existingIds)) {
                HarJadwalPembuatanIk::query()
                    ->where('unit_id', $unitId)
                    ->where('year', $year)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Pembuatan IK Pemeliharaan {$unit->name} Tahun {$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Pembuatan IK Pemeliharaan berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);

        $records = HarJadwalPembuatanIk::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $rows = collect(range(1, 30))->map(fn (int $num): array => [
                'no_urut' => $num,
                'instruksi_kerja' => 'IK......................................................',
                'pic_pembuat' => '',
                'rencana_bulan' => [],
                'realisasi_bulan' => [],
                'jumlah' => 0,
            ])->all();
        } else {
            $rows = $records->map(function (HarJadwalPembuatanIk $r, int $idx): array {
                $rencana = $r->rencana_bulan ?? [];
                $realisasi = $r->realisasi_bulan ?? [];
                $totalTarget = count(array_unique(array_merge($rencana, $realisasi)));

                return [
                    'no_urut' => $r->no_urut ?: ($idx + 1),
                    'instruksi_kerja' => $r->instruksi_kerja,
                    'pic_pembuat' => $r->pic_pembuat ?? '',
                    'rencana_bulan' => $rencana,
                    'realisasi_bulan' => $realisasi,
                    'jumlah' => $totalTarget,
                ];
            })->all();
        }

        // Hitung total per bulan (1..12)
        $monthTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            $count = 0;
            foreach ($rows as $row) {
                if (in_array($m, $row['rencana_bulan']) || in_array($m, $row['realisasi_bulan'])) {
                    $count++;
                }
            }
            $monthTotals[$m] = $count;
        }

        $grandTotalIk = array_sum(array_column($rows, 'jumlah'));

        // Hitung Rekap Rencana & Realisasi
        $totalRencana = 0;
        $totalRealisasi = 0;
        foreach ($rows as $row) {
            $totalRencana += count($row['rencana_bulan']);
            $totalRealisasi += count($row['realisasi_bulan']);
        }
        $kinerja = $totalRencana > 0 ? round(($totalRealisasi / $totalRencana) * 100) : 0;

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        $pdf = Pdf::loadView('har.jadwal.pembuatan-ik-pdf', [
            'unit' => $unit,
            'year' => $year,
            'rows' => $rows,
            'monthTotals' => $monthTotals,
            'grandTotalIk' => $grandTotalIk,
            'totalRencana' => $totalRencana,
            'totalRealisasi' => $totalRealisasi,
            'kinerja' => $kinerja,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_Pembuatan_IK_{$safeUnitName}_{$year}.pdf");
    }
}
