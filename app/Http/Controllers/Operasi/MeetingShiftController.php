<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\OperasiMeetingShiftJadwal;
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
 * Jadwal Meeting Shift (OPERASI). Baris mark (serah terima & meeting pagi/sore)
 * dengan realisasi harian, plus baris shift (huruf jadwal pagi & sore).
 */
class MeetingShiftController extends Controller
{
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

        $saved = OperasiMeetingShiftJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->orderBy('sort_order')->orderBy('id')->get();

        $rows = $saved->isEmpty()
            ? [
                ['id' => null, 'label' => 'Serah Terima Tugas (PAGI)', 'row_type' => 'mark', 'days' => [], 'target' => $daysInMonth],
                ['id' => null, 'label' => 'Meeting Shift (PAGI)', 'row_type' => 'mark', 'days' => [], 'target' => $daysInMonth],
                ['id' => null, 'label' => 'Serah Terima Tugas (SORE)', 'row_type' => 'mark', 'days' => [], 'target' => $daysInMonth],
                ['id' => null, 'label' => 'Meeting Shift (SORE)', 'row_type' => 'mark', 'days' => [], 'target' => $daysInMonth],
                ['id' => null, 'label' => 'PAGI (07.00 - 15.00)', 'row_type' => 'shift', 'days' => [], 'target' => 0],
                ['id' => null, 'label' => 'SORE (15.00 - 23.00)', 'row_type' => 'shift', 'days' => [], 'target' => 0],
            ]
            : $saved->map(fn (OperasiMeetingShiftJadwal $r): array => ['id' => $r->id, 'label' => $r->label, 'row_type' => $r->row_type, 'days' => $r->days ?? [], 'target' => $r->target])->all();

        return Inertia::render('operasi/jadwal/meeting-shift/index', [
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
            'rows.*.label' => ['required', 'string', 'max:255'],
            'rows.*.row_type' => ['required', 'string', 'in:mark,shift'],
            'rows.*.days' => ['nullable', 'array'],
            'rows.*.target' => ['nullable', 'integer', 'min:0'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            OperasiMeetingShiftJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
            foreach ($validated['rows'] ?? [] as $i => $row) {
                $days = collect($row['days'] ?? [])->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')->mapWithKeys(fn ($v, $k): array => [(string) $k => trim((string) $v)])->all();
                OperasiMeetingShiftJadwal::query()->create([
                    'unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'no_urut' => $i + 1,
                    'label' => $row['label'], 'row_type' => $row['row_type'], 'days' => $days,
                    'target' => (int) ($row['target'] ?? 0), 'sort_order' => $i, 'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Jadwal Meeting Shift {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal Meeting Shift berhasil disimpan.']);
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

        return $pdf->download('Jadwal_Meeting_Shift_'.str_replace(' ', '_', $unit->name)."_{$month}_{$year}.pdf");
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

        $rows = OperasiMeetingShiftJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (OperasiMeetingShiftJadwal $r): array {
                $days = $r->days ?? [];
                $re = count(array_filter($days, fn ($v): bool => trim((string) $v) !== ''));

                return [
                    'label' => $r->label,
                    'row_type' => $r->row_type,
                    'days' => $days,
                    'target' => $r->target,
                    'realisasi' => $r->row_type === 'shift' ? null : $re,
                    'performance' => $r->row_type === 'shift' ? null : ($r->target > 0 ? (int) round(($re / $r->target) * 100) : 0),
                ];
            })->all();

        return ['operasi.jadwal.meeting-shift-pdf', [
            'unit' => $unit, 'month' => $month, 'year' => $year, 'monthName' => JadwalPdf::monthName($month),
            'daysInMonth' => $daysInMonth, 'rows' => $rows, ...JadwalPdf::logos(),
        ]];
    }
}
