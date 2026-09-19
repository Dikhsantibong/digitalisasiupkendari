<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Operasi5s5rJadwal;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal Pelaksanaan 5S5R (OPERASI). Tiap pelaksana punya baris RENC & REAL
 * harian; rencana/realisasi = jumlah tanda, kinerja = realisasi ÷ target.
 */
class Program5s5rController extends Controller
{
    public const DEFAULT_PELAKSANA = [
        'OFFICER FUELFACILITY',
        'OPERATOR SHIFT A',
        'OPERATOR SHIFT B',
        'OPERATOR SHIFT C',
        'OPERATOR SHIFT D',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView) || $user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $saved = Operasi5s5rJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->orderBy('sort_order')->orderBy('id')->get();

        $rows = $saved->isEmpty()
            ? collect(self::DEFAULT_PELAKSANA)->map(fn (string $p, int $i): array => ['id' => null, 'pelaksana' => $p, 'rencana' => [], 'realisasi' => [], 'target' => 4])->all()
            : $saved->map(fn (Operasi5s5rJadwal $r): array => ['id' => $r->id, 'pelaksana' => $r->pelaksana, 'rencana' => $r->rencana ?? [], 'realisasi' => $r->realisasi ?? [], 'target' => $r->target])->all();

        return Inertia::render('operasi/jadwal/program-5s-5r/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => ['units' => $units->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'days_in_month' => $daysInMonth,
            'rows' => $rows,
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
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.pelaksana' => ['required', 'string', 'max:255'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.target' => ['nullable', 'integer', 'min:0'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $clean = fn ($d) => collect($d ?? [])->map(fn ($x) => (int) $x)->filter(fn ($x) => $x >= 1 && $x <= 31)->unique()->values()->all();

        DB::transaction(function () use ($validated, $unit, $month, $year, $user, $clean): void {
            Operasi5s5rJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
            foreach ($validated['rows'] ?? [] as $i => $row) {
                Operasi5s5rJadwal::query()->create([
                    'unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'no_urut' => $i + 1,
                    'pelaksana' => $row['pelaksana'], 'rencana' => $clean($row['rencana'] ?? []), 'realisasi' => $clean($row['realisasi'] ?? []),
                    'target' => (int) ($row['target'] ?? 0), 'sort_order' => $i, 'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Jadwal 5S5R {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal Pelaksanaan 5S5R berhasil disimpan.']);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView) || $user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $unit = Unit::query()->with('serviceUnit')->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        return $pdf->download('Jadwal_5S5R_'.str_replace(' ', '_', $unit->name)."_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $rows = Operasi5s5rJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (Operasi5s5rJadwal $r): array {
                $rc = count($r->rencana ?? []);
                $re = count($r->realisasi ?? []);

                return [
                    'pelaksana' => $r->pelaksana,
                    'rencana' => $r->rencana ?? [],
                    'realisasi' => $r->realisasi ?? [],
                    'rencana_count' => $rc,
                    'target' => $r->target,
                    'realisasi_count' => $re,
                    'performance' => $r->target > 0 ? (int) round(($re / $r->target) * 100) : 0,
                ];
            })->all();

        return ['operasi.jadwal.program-5s-5r-pdf', [
            'unit' => $unit, 'month' => $month, 'year' => $year, 'monthName' => JadwalPdf::monthName($month),
            'daysInMonth' => $daysInMonth, 'rows' => $rows, ...JadwalPdf::logos(),
        ]];
    }
}
