<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiResourcePembangkit;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ResourcePembangkitController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganResourcePembangkit) ||
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

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $records = OperasiResourcePembangkit::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('tanggal');

        $rows = [];
        $totalPemakaian = 0;
        $totalPengiriman = 0;
        $latestStokAkhir = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $record = $records->get($d);
            $stokAwal = $record ? (float) $record->stok_awal : 0;
            $pemakaian = $record ? (float) $record->pemakaian : 0;
            $pengiriman = $record ? (float) $record->pengiriman : 0;
            $stokAkhir = $record ? (float) $record->stok_akhir : 0;

            $totalPemakaian += $pemakaian;
            $totalPengiriman += $pengiriman;
            if ($stokAkhir > 0 || $pemakaian > 0 || $pengiriman > 0) {
                $latestStokAkhir = $stokAkhir;
            }

            $rows[] = [
                'tanggal' => $d,
                'stok_awal' => $stokAwal,
                'pemakaian' => $pemakaian,
                'pengiriman' => $pengiriman,
                'stok_akhir' => $stokAkhir,
                'keterangan' => $record?->keterangan ?? '',
            ];
        }

        return Inertia::render('operasi/input/resource-pembangkit/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'rows' => $rows,
            'summary' => [
                'total_pemakaian' => $totalPemakaian,
                'total_pengiriman' => $totalPengiriman,
                'latest_stok_akhir' => $latestStokAkhir,
                'days_count' => $daysInMonth,
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
            ],
            'can_write' => $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganResourcePembangkit),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganResourcePembangkit), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['required', 'array'],
            'rows.*.tanggal' => ['required', 'integer', 'between:1,31'],
            'rows.*.stok_awal' => ['nullable', 'numeric', 'min:0'],
            'rows.*.pemakaian' => ['nullable', 'numeric', 'min:0'],
            'rows.*.pengiriman' => ['nullable', 'numeric', 'min:0'],
            'rows.*.stok_akhir' => ['nullable', 'numeric', 'min:0'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['rows'] as $row) {
            OperasiResourcePembangkit::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => (int) $validated['year'],
                    'month' => (int) $validated['month'],
                    'tanggal' => (int) $row['tanggal'],
                ],
                [
                    'stok_awal' => (float) ($row['stok_awal'] ?? 0),
                    'pemakaian' => (float) ($row['pemakaian'] ?? 0),
                    'pengiriman' => (float) ($row['pengiriman'] ?? 0),
                    'stok_akhir' => (float) ($row['stok_akhir'] ?? 0),
                    'keterangan' => $row['keterangan'] ?? null,
                    'input_by' => $user->id,
                ]
            );
        }

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan laporan resource pembangkit {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan resource pembangkit berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganResourcePembangkit) ||
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

        return $pdf->download("Laporan_Resource_BBM_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $records = OperasiResourcePembangkit::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('tanggal');

        $rows = [];
        $totalPemakaian = 0;
        $totalPengiriman = 0;
        $latestStokAkhir = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $record = $records->get($d);
            $stokAwal = $record ? (float) $record->stok_awal : 0;
            $pemakaian = $record ? (float) $record->pemakaian : 0;
            $pengiriman = $record ? (float) $record->pengiriman : 0;
            $stokAkhir = $record ? (float) $record->stok_akhir : 0;

            $totalPemakaian += $pemakaian;
            $totalPengiriman += $pengiriman;
            if ($stokAkhir > 0 || $pemakaian > 0 || $pengiriman > 0) {
                $latestStokAkhir = $stokAkhir;
            }

            $rows[] = [
                'tanggal' => $d,
                'stok_awal' => $stokAwal,
                'pemakaian' => $pemakaian,
                'pengiriman' => $pengiriman,
                'stok_akhir' => $stokAkhir,
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

        return ['operasi.input.resource-pembangkit-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'rows' => $rows,
            'totalPemakaian' => $totalPemakaian,
            'totalPengiriman' => $totalPengiriman,
            'latestStokAkhir' => $latestStokAkhir,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }
}
