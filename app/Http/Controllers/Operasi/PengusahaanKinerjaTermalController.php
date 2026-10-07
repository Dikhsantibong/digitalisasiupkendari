<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiRekap;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\KinerjaTermalSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Data Kinerja Pembangkit Termal: CF, OF, SF, EAF, POF,
 * FOF, FOR, SOF, EFOR, SdOF and EFF per machine, computed from the other
 * sheets ({@see KinerjaTermalSheet}); any figure may be corrected (only the
 * corrections are stored) or reset to the computed one.
 */
class PengusahaanKinerjaTermalController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly KinerjaTermalSheet $sheet,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $record = $this->sheet->record($unit, $month, $year);

        return Inertia::render('pengusahaan/operasi/kinerja-termal/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            ...$this->sheet->build($unit, $month, $year),
            'catatan' => $record?->catatan ?? '',
            'saved_at' => $record?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'values' => ['nullable', 'array'],
            'values.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $rows = $this->sheet->build($unit, $month, $year)['rows'];

        $record = OperasiRekap::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => KinerjaTermalSheet::JENIS, 'month' => $month, 'year' => $year],
            ['overrides' => $this->sheet->overridesFrom($validated['values'] ?? [], $rows), 'catatan' => $validated['catatan'] ?? null, 'input_by' => $user->id],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "Data Kinerja Pembangkit Termal {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', 'Data kinerja pembangkit termal berhasil disimpan.');
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);
        [$month, $year] = $this->period($request);

        $pdf = Pdf::loadView('operasi.kinerja-termal.pdf', [
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            ...$this->sheet->build($unit, $month, $year),
            'catatan' => $this->sheet->record($unit, $month, $year)?->catatan,
            'manager' => $unit->manager()?->name,
            'signed_on' => Carbon::create($year, $month, 1)->addMonth(),
        ])->setPaper('a4', 'landscape');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("Kinerja_Pembangkit_Termal_{$unit->name}_{$month}_{$year}.pdf");
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
