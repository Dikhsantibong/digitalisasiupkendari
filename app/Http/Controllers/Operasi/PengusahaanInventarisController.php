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
use App\Services\Operasi\InventarisMesinSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Daftar Inventarisasi Mesin: the Master Mesin data of
 * the unit with the month's daya mampu & beban tertinggi (Beban Tertinggi);
 * mampu / beban can be corrected and each machine gets a keterangan.
 */
class PengusahaanInventarisController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly InventarisMesinSheet $sheet,
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

        return Inertia::render('pengusahaan/operasi/inventaris-mesin/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            ...$this->sheet->build($unit, $month, $year),
            'saved_at' => $record?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
            'can_manage_machines' => $user->hasPermissionTo(PermissionName::MachineUpdate),
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
            'values.*.mampu' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'values.*.beban' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'values.*.ket' => ['nullable', 'string', 'max:200'],
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        $record = OperasiRekap::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => InventarisMesinSheet::JENIS, 'month' => $month, 'year' => $year],
            ['overrides' => $this->sheet->overridesFrom($validated['values'] ?? [], $this->sheet->build($unit, $month, $year)['rows']), 'input_by' => $user->id],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "Daftar Inventarisasi Mesin {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', 'Daftar inventarisasi mesin berhasil disimpan.');
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);
        [$month, $year] = $this->period($request);

        $pdf = Pdf::loadView('operasi.inventaris-mesin.pdf', [
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            ...$this->sheet->build($unit, $month, $year),
        ])->setPaper('a4', 'landscape');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("Inventarisasi_Mesin_{$unit->name}_{$month}_{$year}.pdf");
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
