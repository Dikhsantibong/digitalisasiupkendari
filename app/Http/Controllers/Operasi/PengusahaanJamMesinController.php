<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\JamMesinJenis;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiJamMesin;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\JamMesinSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Jam Operasi, Jam Pemeliharaan (PO) and Jam Gangguan:
 * hours per mesin per tanggal. Each route passes its sheet as the `jenis`
 * default; the page can take the hours recorded in Star-Stop as a starting
 * point. Jam Siap Operasi is derived from the three ({@see PengusahaanJamSiapOpsController}).
 */
class PengusahaanJamMesinController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly JamMesinSheet $sheet,
    ) {}

    public function index(Request $request, JamMesinJenis $jenis): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $machines = $this->sheet->machines($unit);
        $record = $this->sheet->record($unit, $jenis, $month, $year);

        return Inertia::render("pengusahaan/operasi/jam-{$jenis->value}/index", [
            'jenis' => $jenis->value,
            'title' => $jenis->label(),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'readings' => (object) $this->sheet->readings($unit, $jenis, $month, $year, $machines),
            'star_stop' => (object) $this->sheet->fromStarStop($unit, $jenis, $month, $year, $machines),
            'catatan' => $record?->catatan ?? '',
            'saved_at' => $record?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request, JamMesinJenis $jenis): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'readings' => ['nullable', 'array'],
            'readings.*' => ['array'],
            'readings.*.*' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'readings.*.*.max' => 'Jam per hari paling banyak 24.',
            'readings.*.*.min' => 'Jam tidak boleh negatif.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $machines = $this->sheet->machines($unit);
        $readings = $this->sheet->sanitize($validated['readings'] ?? [], $machines, Carbon::create($year, $month, 1)->daysInMonth);
        $summary = $this->sheet->summarize($readings, $machines);

        $record = OperasiJamMesin::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => $jenis, 'month' => $month, 'year' => $year],
            [
                'readings' => $readings,
                'totals_by_machine' => $summary['totals_by_machine'],
                'grand_total' => $summary['grand_total'],
                'catatan' => $validated['catatan'] ?? null,
                'input_by' => $user->id,
            ],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "{$jenis->label()} {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', "{$jenis->label()} berhasil disimpan.");
    }

    public function pdf(Request $request, JamMesinJenis $jenis): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $machines = $this->sheet->machines($unit);
        $readings = $this->sheet->readings($unit, $jenis, $month, $year, $machines);
        $summary = $this->sheet->summarize($readings, $machines);

        $pdf = Pdf::loadView('operasi.jam-mesin.pdf', [
            'unit' => $unit,
            'title' => $jenis->title(),
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'readings' => $readings,
            'totals_by_machine' => $summary['totals_by_machine'],
            'grand_total' => $summary['grand_total'],
            'blank_zero' => true,
            'catatan' => $this->sheet->record($unit, $jenis, $month, $year)?->catatan,
        ])->setPaper('a4', 'portrait');

        $file = str_replace(' ', '_', $jenis->label());
        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("{$file}_{$unit->name}_{$month}_{$year}.pdf");
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            max(2020, min(2100, (int) ($request->integer('year') ?: $now->year))),
        ];
    }
}
