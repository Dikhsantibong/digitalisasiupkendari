<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\OperasiFlmJadwal;
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

/**
 * Jadwal First Line Maintenance (FLM) Operator (modul OPERASI). Matriks harian
 * RUTIN / NON RUTIN per shift: baris mark (klik isi 1 → realisasi = jumlah),
 * baris minutes (menit → realisasi = total menit), dan baris shift (huruf jadwal).
 */
class FlmController extends Controller
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
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $saved = OperasiFlmJadwal::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        $rows = $saved->isEmpty()
            ? $this->defaultRows($daysInMonth)
            : $saved->map(fn (OperasiFlmJadwal $r): array => [
                'id' => $r->id, 'section' => $r->section, 'shift' => $r->shift ?? '', 'label' => $r->label,
                'row_type' => $r->row_type, 'days' => $r->days ?? [], 'target' => $r->target,
            ])->all();

        return Inertia::render('operasi/jadwal/flm/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name, 'service_unit_name' => $unit->serviceUnit?->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => ['units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'days_in_month' => $daysInMonth,
            'rows' => $rows,
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.section' => ['required', 'string', 'in:rutin,non_rutin'],
            'rows.*.shift' => ['nullable', 'string', 'max:30'],
            'rows.*.label' => ['required', 'string', 'max:100'],
            'rows.*.row_type' => ['required', 'string', 'in:mark,minutes,shift'],
            'rows.*.days' => ['nullable', 'array'],
            'rows.*.target' => ['nullable', 'integer', 'min:0'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            OperasiFlmJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $days = collect($row['days'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')
                    ->mapWithKeys(fn ($v, $k): array => [(string) $k => trim((string) $v)])
                    ->all();

                OperasiFlmJadwal::query()->create([
                    'unit_id' => $unit->id, 'year' => $year, 'month' => $month,
                    'section' => $row['section'], 'shift' => $row['shift'] ?? null, 'label' => $row['label'],
                    'row_type' => $row['row_type'], 'days' => $days, 'target' => (int) ($row['target'] ?? 0),
                    'sort_order' => $index, 'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Jadwal FLM Operator {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal FLM Operator berhasil disimpan.']);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_FLM_{$safeUnitName}_{$month}_{$year}.pdf");
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

        $saved = OperasiFlmJadwal::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        $source = $saved->isEmpty()
            ? collect($this->defaultRows($daysInMonth))
            : $saved->map(fn (OperasiFlmJadwal $r): array => [
                'section' => $r->section, 'shift' => $r->shift ?? '', 'label' => $r->label,
                'row_type' => $r->row_type, 'days' => $r->days ?? [], 'target' => $r->target,
            ]);

        $rows = $source->map(function (array $r) use ($daysInMonth): array {
            [$realisasi] = $this->analyse($r['row_type'], $r['days'], $daysInMonth);
            $r['realisasi'] = $realisasi;
            $r['percentage'] = $r['row_type'] === 'shift' ? null : ((int) $r['target'] > 0 ? (int) round(($realisasi / (int) $r['target']) * 100) : 0);

            return $r;
        })->all();

        $monthNames = [1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'];
        $monthName = $monthNames[$month] ?? '';

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['operasi.jadwal.flm-pdf', [
            'unit' => $unit, 'month' => $month, 'year' => $year, 'monthName' => $monthName,
            'daysInMonth' => $daysInMonth, 'rows' => $rows, 'logoLeft' => $logoLeft, 'logoRight' => $logoRight,
        ]];
    }

    /**
     * Compute realisasi from a row's day map.
     *
     * @param  array<string, string>  $days
     * @return array{0: int}
     */
    private function analyse(string $rowType, array $days, int $daysInMonth): array
    {
        if ($rowType === 'shift') {
            return [0];
        }

        $realisasi = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $v = $days[(string) $d] ?? '';
            if (trim((string) $v) === '') {
                continue;
            }
            $realisasi += $rowType === 'minutes' ? (int) $v : 1;
        }

        return [$realisasi];
    }

    /**
     * The default FLM matrix reproduced when a period has no saved rows yet.
     *
     * @return list<array<string, mixed>>
     */
    private function defaultRows(int $daysInMonth): array
    {
        $rows = [];
        // RUTIN
        $rows[] = ['id' => null, 'section' => 'rutin', 'shift' => 'JADWAL', 'label' => 'REALISASI', 'row_type' => 'mark', 'days' => [], 'target' => $daysInMonth];
        $rows[] = ['id' => null, 'section' => 'rutin', 'shift' => '', 'label' => '08:00 sd 15:00', 'row_type' => 'shift', 'days' => [], 'target' => 0];
        // NON RUTIN per shift
        foreach (['A', 'B', 'C', 'D'] as $shift) {
            $rows[] = ['id' => null, 'section' => 'non_rutin', 'shift' => $shift, 'label' => 'REALISASI', 'row_type' => 'mark', 'days' => [], 'target' => 15];
            $rows[] = ['id' => null, 'section' => 'non_rutin', 'shift' => $shift, 'label' => 'Waktu tentatif', 'row_type' => 'minutes', 'days' => [], 'target' => 900];
        }

        return $rows;
    }
}
