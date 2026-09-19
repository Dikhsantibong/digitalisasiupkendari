<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\KondisiAbnormal;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class KondisiAbnormalController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $records = KondisiAbnormal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('no_urut')
            ->get()
            ->keyBy('no_urut');

        $rows = [];
        $totalAbnormalCount = 0;
        $totalAbnormalDurasi = 0;
        $totalGangguanCount = 0;
        $totalGangguanDurasi = 0;

        $rowCount = max(30, $records->keys()->max() ?? 30);

        for ($i = 1; $i <= $rowCount; $i++) {
            $rec = $records->get($i);
            $isAbnormal = $rec ? (int) $rec->is_abnormal : 0;
            $durasiAbnormal = $rec ? (float) $rec->durasi_abnormal : 0;
            $isGangguan = $rec ? (int) $rec->is_gangguan : 0;
            $durasiGangguan = $rec ? (float) $rec->durasi_gangguan : 0;

            if ($isAbnormal === 1) {
                $totalAbnormalCount += 1;
            }
            $totalAbnormalDurasi += $durasiAbnormal;

            if ($isGangguan === 1) {
                $totalGangguanCount += 1;
            }
            $totalGangguanDurasi += $durasiGangguan;

            $rows[] = [
                'id' => $rec?->id,
                'no_urut' => $i,
                'uraian_kondisi' => $rec?->uraian_kondisi ?? '',
                'tanggal' => $rec?->tanggal?->format('Y-m-d') ?? '',
                'is_abnormal' => $isAbnormal,
                'durasi_abnormal' => $durasiAbnormal,
                'is_gangguan' => $isGangguan,
                'durasi_gangguan' => $durasiGangguan,
            ];
        }

        return Inertia::render('operasi/input/kondisi-abnormal', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'rows' => $rows,
            'summary' => [
                'total_abnormal_count' => $totalAbnormalCount,
                'total_abnormal_durasi' => $totalAbnormalDurasi,
                'total_gangguan_count' => $totalGangguanCount,
                'total_gangguan_durasi' => $totalGangguanDurasi,
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['required', 'array'],
            'rows.*.no_urut' => ['required', 'integer', 'min:1'],
            'rows.*.uraian_kondisi' => ['nullable', 'string'],
            'rows.*.tanggal' => ['nullable', 'date'],
            'rows.*.is_abnormal' => ['nullable', 'integer', 'in:0,1'],
            'rows.*.durasi_abnormal' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_gangguan' => ['nullable', 'integer', 'in:0,1'],
            'rows.*.durasi_gangguan' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($validated['rows'] as $row) {
            $hasData = ! empty(trim($row['uraian_kondisi'] ?? '')) ||
                ! empty($row['tanggal']) ||
                ! empty($row['is_abnormal']) ||
                ! empty($row['durasi_abnormal']) ||
                ! empty($row['is_gangguan']) ||
                ! empty($row['durasi_gangguan']);

            if (! $hasData) {
                KondisiAbnormal::query()
                    ->where('unit_id', $unit->id)
                    ->where('year', (int) $validated['year'])
                    ->where('month', (int) $validated['month'])
                    ->where('no_urut', (int) $row['no_urut'])
                    ->delete();

                continue;
            }

            KondisiAbnormal::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => (int) $validated['year'],
                    'month' => (int) $validated['month'],
                    'no_urut' => (int) $row['no_urut'],
                ],
                [
                    'uraian_kondisi' => $row['uraian_kondisi'] ?? null,
                    'tanggal' => $row['tanggal'] ? Carbon::parse($row['tanggal'])->toDateString() : null,
                    'is_abnormal' => (int) ($row['is_abnormal'] ?? 0),
                    'durasi_abnormal' => (float) ($row['durasi_abnormal'] ?? 0),
                    'is_gangguan' => (int) ($row['is_gangguan'] ?? 0),
                    'durasi_gangguan' => (float) ($row['durasi_gangguan'] ?? 0),
                    'input_by' => $user->id,
                ]
            );
        }

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan laporan kondisi abnormal dan gangguan pembangkit {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan kondisi abnormal & gangguan berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Laporan_Kondisi_Abnormal_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = KondisiAbnormal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('no_urut')
            ->get()
            ->keyBy('no_urut');

        $rows = [];
        $totalAbnormalCount = 0;
        $totalAbnormalDurasi = 0;
        $totalGangguanCount = 0;
        $totalGangguanDurasi = 0;

        $rowCount = max(30, $records->keys()->max() ?? 30);

        for ($i = 1; $i <= $rowCount; $i++) {
            $rec = $records->get($i);
            $isAbnormal = $rec ? (int) $rec->is_abnormal : 0;
            $durasiAbnormal = $rec ? (float) $rec->durasi_abnormal : 0;
            $isGangguan = $rec ? (int) $rec->is_gangguan : 0;
            $durasiGangguan = $rec ? (float) $rec->durasi_gangguan : 0;

            if ($isAbnormal === 1) {
                $totalAbnormalCount += 1;
            }
            $totalAbnormalDurasi += $durasiAbnormal;

            if ($isGangguan === 1) {
                $totalGangguanCount += 1;
            }
            $totalGangguanDurasi += $durasiGangguan;

            $rows[] = [
                'no_urut' => $i,
                'uraian_kondisi' => $rec?->uraian_kondisi ?? '',
                'tanggal' => $rec?->tanggal?->format('d/m/Y') ?? '',
                'is_abnormal' => $isAbnormal === 1 ? '1' : '',
                'durasi_abnormal' => $durasiAbnormal > 0 ? $durasiAbnormal : '',
                'is_gangguan' => $isGangguan === 1 ? '1' : '',
                'durasi_gangguan' => $durasiGangguan > 0 ? $durasiGangguan : '',
            ];
        }

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];
        $monthName = $monthNames[$month] ?? '';

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['operasi.input.kondisi-abnormal-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'rows' => $rows,
            'totalAbnormalCount' => $totalAbnormalCount,
            'totalAbnormalDurasi' => $totalAbnormalDurasi,
            'totalGangguanCount' => $totalGangguanCount,
            'totalGangguanDurasi' => $totalGangguanDurasi,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }
}
