<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Operasi5s5rJadwal;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operator\AttendanceRoster;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal Pelaksanaan 5S5R (OPERASI). Pelaksana default ditarik dari operator
 * aktif masing-masing shift (Regu A–D) pada unit, dan dapat di-custom bebas
 * (pilih operator/pegawai unit maupun ketik nama pelaksana sendiri).
 */
class Program5s5rController extends Controller
{
    /** Fallback ketika unit belum memiliki data operator shift di Master Pegawai. */
    public const DEFAULT_PELAKSANA = [
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

        $shiftOperators = self::shiftOperatorsFor($unit->id);
        $otherEmployees = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('regu')->orWhere('regu', ''))
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'regu', 'is_shift_leader']);

        $saved = Operasi5s5rJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = $saved->isEmpty()
            ? self::defaultRowsFor($unit->id, $shiftOperators)
            : $saved->map(fn (Operasi5s5rJadwal $r): array => [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'shift' => $r->shift,
                'pelaksana' => $r->pelaksana,
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'target' => $r->target,
            ])->all();

        $formatEmployee = fn (Employee $e): array => [
            'id' => $e->id,
            'name' => $e->name,
            'nip' => $e->nip,
            'position' => $e->position,
            'regu' => $e->regu,
            'is_shift_leader' => (bool) $e->is_shift_leader,
        ];

        return Inertia::render('operasi/jadwal/program-5s-5r/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
                'shifts' => AttendanceRoster::REGU,
                'operators' => $shiftOperators->map($formatEmployee)->values()->all(),
                'employees' => $otherEmployees->map($formatEmployee)->values()->all(),
            ],
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
            'rows.*.employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('unit_id', $unit->id),
            ],
            'rows.*.shift' => ['nullable', 'string', 'max:20'],
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
                $shift = trim((string) ($row['shift'] ?? ''));
                Operasi5s5rJadwal::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $i + 1,
                    'employee_id' => isset($row['employee_id']) && $row['employee_id'] !== '' ? (int) $row['employee_id'] : null,
                    'shift' => $shift !== '' ? $shift : null,
                    'pelaksana' => trim((string) $row['pelaksana']),
                    'rencana' => $clean($row['rencana'] ?? []),
                    'realisasi' => $clean($row['realisasi'] ?? []),
                    'target' => (int) ($row['target'] ?? 0),
                    'sort_order' => $i,
                    'input_by' => $user->id,
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

        $saved = Operasi5s5rJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = $saved->isEmpty()
            ? array_map(fn (array $r): array => [
                'shift' => $r['shift'],
                'pelaksana' => $r['pelaksana'],
                'rencana' => [],
                'realisasi' => [],
                'rencana_count' => 0,
                'target' => $r['target'],
                'realisasi_count' => 0,
                'performance' => 0,
            ], self::defaultRowsFor($unit->id))
            : $saved->map(function (Operasi5s5rJadwal $r): array {
                $rc = count($r->rencana ?? []);
                $re = count($r->realisasi ?? []);

                return [
                    'shift' => $r->shift,
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

    /**
     * Active shift operators of a unit, ordered by shift/regu (A–D), shift leader first.
     *
     * @return EloquentCollection<int, Employee>
     */
    public static function shiftOperatorsFor(int $unitId): EloquentCollection
    {
        return Employee::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->whereNotNull('regu')
            ->where('regu', '!=', '')
            ->orderBy('regu')
            ->orderByDesc('is_shift_leader')
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'regu', 'is_shift_leader']);
    }

    /**
     * Default 5S5R schedule rows built from the unit's active shift operators,
     * falling back to generic shift placeholders only if the unit has no shift operators.
     *
     * @param  EloquentCollection<int, Employee>|null  $operators
     * @return list<array{id: null, employee_id: int|null, shift: string|null, pelaksana: string, rencana: list<int>, realisasi: list<int>, target: int}>
     */
    public static function defaultRowsFor(int $unitId, ?EloquentCollection $operators = null): array
    {
        $operators ??= self::shiftOperatorsFor($unitId);

        if ($operators->isNotEmpty()) {
            return $operators->map(fn (Employee $e): array => [
                'id' => null,
                'employee_id' => $e->id,
                'shift' => $e->regu,
                'pelaksana' => $e->name,
                'rencana' => [],
                'realisasi' => [],
                'target' => 4,
            ])->values()->all();
        }

        return collect(AttendanceRoster::REGU)->map(fn (string $regu): array => [
            'id' => null,
            'employee_id' => null,
            'shift' => $regu,
            'pelaksana' => "OPERATOR SHIFT {$regu}",
            'rencana' => [],
            'realisasi' => [],
            'target' => 4,
        ])->values()->all();
    }
}
