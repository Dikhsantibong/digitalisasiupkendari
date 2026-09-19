<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\OperasiPermitToWork;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PermitToWorkController extends Controller
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

        $records = OperasiPermitToWork::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('no_urut')
            ->get()
            ->keyBy('no_urut');

        $rows = [];
        $totalOpen = 0;
        $totalClose = 0;

        $rowCount = max(30, $records->keys()->max() ?? 30);

        for ($i = 1; $i <= $rowCount; $i++) {
            $rec = $records->get($i);
            $hasData = $rec && (! empty($rec->uraian) || ! empty($rec->tanggal));
            $status = $rec ? strtolower($rec->status) : 'open';

            if ($hasData) {
                if ($status === 'close') {
                    $totalClose += 1;
                } else {
                    $totalOpen += 1;
                }
            }

            $rows[] = [
                'id' => $rec?->id,
                'no_urut' => $i,
                'uraian' => $rec?->uraian ?? '',
                'tanggal' => $rec?->tanggal?->format('Y-m-d') ?? '',
                'status' => $status,
            ];
        }

        $totalItems = $totalOpen + $totalClose;

        return Inertia::render('operasi/input/permit-to-work', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'rows' => $rows,
            'summary' => [
                'total_ptw' => $totalItems,
                'total_open' => $totalOpen,
                'total_close' => $totalClose,
                'completion_rate' => $totalItems > 0 ? round(($totalClose / $totalItems) * 100, 1) : 0,
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
            'rows.*.uraian' => ['nullable', 'string'],
            'rows.*.tanggal' => ['nullable', 'date'],
            'rows.*.status' => ['required', 'string', 'in:open,close'],
        ]);

        foreach ($validated['rows'] as $row) {
            $hasData = ! empty(trim($row['uraian'] ?? '')) || ! empty($row['tanggal']);

            if (! $hasData) {
                OperasiPermitToWork::query()
                    ->where('unit_id', $unit->id)
                    ->where('year', (int) $validated['year'])
                    ->where('month', (int) $validated['month'])
                    ->where('no_urut', (int) $row['no_urut'])
                    ->delete();

                continue;
            }

            OperasiPermitToWork::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => (int) $validated['year'],
                    'month' => (int) $validated['month'],
                    'no_urut' => (int) $row['no_urut'],
                ],
                [
                    'uraian' => $row['uraian'] ?? null,
                    'tanggal' => $row['tanggal'] ? Carbon::parse($row['tanggal'])->toDateString() : null,
                    'status' => strtolower($row['status']),
                    'input_by' => $user->id,
                ]
            );
        }

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan laporan permit to work {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan Permit to Work berhasil disimpan.']);

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

        return $pdf->download("Laporan_PTW_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = OperasiPermitToWork::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('no_urut')
            ->get()
            ->keyBy('no_urut');

        $rows = [];
        $totalOpen = 0;
        $totalClose = 0;

        $rowCount = max(30, $records->keys()->max() ?? 30);

        for ($i = 1; $i <= $rowCount; $i++) {
            $rec = $records->get($i);
            $hasData = $rec && (! empty($rec->uraian) || ! empty($rec->tanggal));
            $status = $rec ? strtolower($rec->status) : 'open';

            $isOpen = $hasData && $status === 'open';
            $isClose = $hasData && $status === 'close';

            if ($isOpen) {
                $totalOpen += 1;
            }
            if ($isClose) {
                $totalClose += 1;
            }

            $rows[] = [
                'no_urut' => $i,
                'uraian' => $rec?->uraian ?? '',
                'tanggal' => $rec?->tanggal?->format('d/m/Y') ?? '',
                'open' => $isOpen ? '✓' : '',
                'close' => $isClose ? '✓' : '',
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

        return ['operasi.input.permit-to-work-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'rows' => $rows,
            'totalOpen' => $totalOpen,
            'totalClose' => $totalClose,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }
}
