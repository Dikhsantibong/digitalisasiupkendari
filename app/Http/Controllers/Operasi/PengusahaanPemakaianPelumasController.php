<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiPemakaianPelumas;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\PemakaianPelumasSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Pemakaian Pelumas: the monthly sheet of pelumas used
 * per jenis pelumas × mesin per tanggal. The jenis pelumas come from the
 * unit's master (Data Master Operasi → Jenis Pelumas), so every unit has its
 * own columns; TL & Staf Operasi fill it, Manager UL and the UP read it.
 */
class PengusahaanPemakaianPelumasController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly PemakaianPelumasSheet $sheet,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        [$month, $year] = $this->period($request);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $columns = $this->sheet->columns($unit);
        $record = $this->record($unit, $month, $year);
        $readings = $this->sheet->sanitize($record?->raw_readings ?? [], $columns, $daysInMonth);
        $ganti = $this->sheet->sanitize($record?->readings_ganti ?? [], $columns, $daysInMonth);
        $summary = $this->sheet->summarize($readings, $columns, $daysInMonth);

        return Inertia::render('pengusahaan/operasi/pemakaian-pelumas/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'lubricants' => $columns,
            'days_in_month' => $daysInMonth,
            'periods' => $this->sheet->periods($daysInMonth),
            'readings' => (object) $this->sheet->tambahOf($readings, $ganti),
            'readings_ganti' => (object) $ganti,
            'totals_by_lubricant' => (object) $summary['totals_by_lubricant'],
            'grand_total_liter' => $summary['grand_total'],
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
            'readings.*.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'readings_ganti' => ['nullable', 'array'],
            'readings_ganti.*' => ['array'],
            'readings_ganti.*.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $columns = $this->sheet->columns($unit);
        // `readings` is the pelumas tambah, `readings_ganti` the pelumas ganti; the sheet total is both.
        $ganti = $this->sheet->sanitize($validated['readings_ganti'] ?? [], $columns, $daysInMonth);
        $readings = $this->sheet->combine($this->sheet->sanitize($validated['readings'] ?? [], $columns, $daysInMonth), $ganti);
        $summary = $this->sheet->summarize($readings, $columns, $daysInMonth);

        $record = DB::transaction(function () use ($unit, $month, $year, $readings, $ganti, $summary, $validated, $user): OperasiPemakaianPelumas {
            $record = OperasiPemakaianPelumas::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
                [
                    'raw_readings' => $readings,
                    'readings_ganti' => $ganti,
                    'totals_by_lubricant' => $summary['totals_by_lubricant'],
                    'totals_by_machine' => $summary['totals_by_machine'],
                    'grand_total_liter' => $summary['grand_total'],
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ],
            );

            $record->items()->delete();
            $record->items()->createMany($summary['items']);

            return $record;
        });

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "Pemakaian Pelumas {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', 'Pemakaian pelumas berhasil disimpan.');
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
        $record = $this->record($unit, $month, $year);
        $readings = $this->sheet->sanitize($record?->raw_readings ?? [], $columns, $daysInMonth);
        $summary = $this->sheet->summarize($readings, $columns, $daysInMonth);

        $pdf = Pdf::loadView('operasi.pemakaian-pelumas.pdf', [
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'chunks' => $this->sheet->chunkForPrint($columns),
            'periods' => $this->sheet->periods($daysInMonth),
            'readings' => $readings,
            'summary' => $summary,
            'catatan' => $record?->catatan ?? '',
            'keyOf' => fn (int $lubricantId, int $machineId): string => $this->sheet->key($lubricantId, $machineId),
        ])->setPaper('a4', 'landscape');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("Pemakaian_Pelumas_{$unit->name}_{$month}_{$year}.pdf");
    }

    /**
     * Pelumas Tambah & Ganti: read-only, per mesin per tanggal, from the
     * tambah / ganti split of Pemakaian Pelumas.
     */
    public function tambahGanti(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        [$month, $year] = $this->period($request);

        return Inertia::render('pengusahaan/operasi/pelumas-tambah-ganti/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            ...$this->sheet->tambahGanti($unit, $month, $year),
        ]);
    }

    public function tambahGantiPdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $data = $this->sheet->tambahGanti($unit, $month, $year);
        $jenis = $request->query('jenis') === 'ganti' ? 'ganti' : 'tambah';
        $lubricantId = in_array($request->integer('lubricant'), array_column($data['lubricants'], 'id'), true) ? $request->integer('lubricant') : null;
        $shown = array_values(array_filter($data['lubricants'], fn (array $l): bool => $lubricantId === null || $l['id'] === $lubricantId));

        $values = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['machines'] as $machine) {
                for ($day = 1; $day <= $data['days_in_month']; $day++) {
                    $values[$machine['id']][$day] = round(array_sum(array_map(
                        fn (array $l): float => (float) ($data[$jenis][$this->sheet->key($l['id'], $machine['id'])][$day] ?? 0),
                        $shown,
                    )), 2);
                }
            }
        }

        $pdf = Pdf::loadView('operasi.pemakaian-pelumas.tambah-ganti-pdf', [
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'title' => 'PEMAKAIAN PELUMAS '.strtoupper($jenis).($lubricantId ? ' - '.strtoupper($shown[0]['name']) : '').' (LTR)',
            'groups' => $data['groups'],
            'values' => $values,
            'days_in_month' => $data['days_in_month'],
        ])->setPaper('a4', 'portrait');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}('Pelumas_'.ucfirst($jenis)."_{$unit->name}_{$month}_{$year}.pdf");
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

    private function record(Unit $unit, int $month, int $year): ?OperasiPemakaianPelumas
    {
        return OperasiPemakaianPelumas::query()
            ->where('unit_id', $unit->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }
}
