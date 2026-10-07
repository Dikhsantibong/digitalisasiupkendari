<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiPemakaianBbm;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\PemakaianBbmSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Pemakaian Bahan Bakar: liters of BBM used per jenis
 * BBM × mesin per tanggal, for the whole month (no periods). The jenis BBM
 * and machines come from the unit's master; the page can take the daily
 * PEMAKAIAN of Stand Flow Meter as a starting point. Feeds TUG BBM.
 */
class PengusahaanPemakaianBbmController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly PemakaianBbmSheet $sheet,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $columns = $this->sheet->columns($unit);
        $record = $this->record($unit, $month, $year);
        $readings = $this->sheet->sanitize($record?->raw_readings ?? [], $columns, $daysInMonth);

        return Inertia::render('pengusahaan/operasi/pemakaian-bbm/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'fuels' => $columns,
            'days_in_month' => $daysInMonth,
            'readings' => (object) $readings,
            'stand_meter' => (object) $this->sheet->fromStandMeter($unit, $month, $year, $columns, $daysInMonth),
            'catatan' => $record?->catatan ?? '',
            'saved_at' => $record?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
            'can_manage_master' => $user->hasPermissionTo(PermissionName::OperasiMasterManage),
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
            'readings' => ['nullable', 'array'],
            'readings.*' => ['array'],
            'readings.*.*' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $columns = $this->sheet->columns($unit);
        $readings = $this->sheet->sanitize($validated['readings'] ?? [], $columns, Carbon::create($year, $month, 1)->daysInMonth);
        $summary = $this->sheet->summarize($readings, $columns);

        $record = OperasiPemakaianBbm::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            [
                'raw_readings' => $readings,
                'totals_by_fuel' => $summary['totals_by_fuel'],
                'totals_by_machine' => $summary['totals_by_machine'],
                'grand_total_liter' => $summary['grand_total'],
                'catatan' => $validated['catatan'] ?? null,
                'input_by' => $user->id,
            ],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "Pemakaian Bahan Bakar {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', 'Pemakaian bahan bakar berhasil disimpan.');
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $columns = $this->sheet->columns($unit);
        // ?fuel=HSD prints one jenis BBM (the Laporan Pengusahaan has a page per jenis).
        if ($request->filled('fuel')) {
            $columns = array_values(array_filter($columns, fn (array $fuel): bool => $fuel['code'] === $request->query('fuel')));
        }
        $record = $this->record($unit, $month, $year);
        $readings = $this->sheet->sanitize($record?->raw_readings ?? [], $columns, $daysInMonth);

        $pdf = Pdf::loadView('operasi.pemakaian-bbm.pdf', [
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'fuels' => $columns,
            'days_in_month' => $daysInMonth,
            'readings' => $readings,
            'summary' => $this->sheet->summarize($readings, $columns),
            'catatan' => $record?->catatan ?? '',
            'keyOf' => fn (string $code, int $machineId): string => $this->sheet->key($code, $machineId),
        ])->setPaper('a4', 'portrait');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("Pemakaian_Bahan_Bakar_{$unit->name}_{$month}_{$year}.pdf");
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

    private function record(Unit $unit, int $month, int $year): ?OperasiPemakaianBbm
    {
        return OperasiPemakaianBbm::query()->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();
    }
}
