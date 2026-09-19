<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HarJadwalPatrolCheck;
use App\Models\Holiday;
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

class JadwalPatrolCheckController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
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

        // Ambil operator masing-masing unit selain Manager UL, Staf, dan seluruh TL
        $operators = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('position', 'like', '%operator%')
                    ->orWhere(function ($sub): void {
                        $sub->where('position', 'not like', '%manager%')
                            ->where('position', 'not like', '%manajer%')
                            ->where('position', 'not like', '%staf%')
                            ->where('position', 'not like', '%staff%')
                            ->where('position', 'not like', '%team leader%')
                            ->where('position', 'not like', '%tl %')
                            ->where('position', 'not like', 'tl%')
                            ->where('position', 'not like', '%tl');
                    });
            })
            ->where('position', 'not like', '%manager%')
            ->where('position', 'not like', '%manajer%')
            ->where('position', 'not like', '%staf%')
            ->where('position', 'not like', '%staff%')
            ->where('position', 'not like', '%team leader%')
            ->where('position', 'not like', '%tl %')
            ->where('position', 'not like', 'tl%')
            ->orderByRaw('regu is null, regu')
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'regu']);

        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isWeekend = $date->isSaturday() || $date->isSunday();
            $isHoliday = $holiday !== null;

            return [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_red' => $isWeekend || $isHoliday,
                'holiday' => $holiday?->description,
            ];
        })->all();

        $targetWorkingDays = collect($days)->where('is_red', false)->count();

        $savedRecords = HarJadwalPatrolCheck::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('employee_id');

        $rows = $operators->map(function (Employee $employee) use ($savedRecords): array {
            $saved = $savedRecords->get($employee->id);

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'nip' => $employee->nip,
                'position' => $employee->position,
                'regu' => $employee->regu,
                'rencana' => $saved?->rencana ?? [],
                'realisasi' => $saved?->realisasi ?? [],
            ];
        })->all();

        return Inertia::render('har/jadwal/patrol-check/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'days' => $days,
            'target_working_days' => $targetWorkingDays,
            'rows' => $rows,
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.employee_id' => ['required', 'integer'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year): void {
            foreach ($validated['rows'] ?? [] as $row) {
                $rencana = collect($row['rencana'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')
                    ->all();

                $realisasi = collect($row['realisasi'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')
                    ->all();

                HarJadwalPatrolCheck::query()->updateOrCreate(
                    [
                        'unit_id' => $unitId,
                        'employee_id' => (int) $row['employee_id'],
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'rencana' => $rencana,
                        'realisasi' => $realisasi,
                        'input_by' => $user->id,
                    ]
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Piket Patrol Check Harian {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Piket Patrol Check Harian berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $operators = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('position', 'like', '%operator%')
                    ->orWhere(function ($sub): void {
                        $sub->where('position', 'not like', '%manager%')
                            ->where('position', 'not like', '%manajer%')
                            ->where('position', 'not like', '%staf%')
                            ->where('position', 'not like', '%staff%')
                            ->where('position', 'not like', '%team leader%')
                            ->where('position', 'not like', '%tl %')
                            ->where('position', 'not like', 'tl%')
                            ->where('position', 'not like', '%tl');
                    });
            })
            ->where('position', 'not like', '%manager%')
            ->where('position', 'not like', '%manajer%')
            ->where('position', 'not like', '%staf%')
            ->where('position', 'not like', '%staff%')
            ->where('position', 'not like', '%team leader%')
            ->where('position', 'not like', '%tl %')
            ->where('position', 'not like', 'tl%')
            ->orderByRaw('regu is null, regu')
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'regu']);

        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isWeekend = $date->isSaturday() || $date->isSunday();
            $isHoliday = $holiday !== null;

            return [
                'day' => $day,
                'is_red' => $isWeekend || $isHoliday,
            ];
        })->all();

        $targetWorkingDays = collect($days)->where('is_red', false)->count();

        $savedRecords = HarJadwalPatrolCheck::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('employee_id');

        $totalRencana = 0;
        $totalRealisasi = 0;

        $rows = $operators->map(function (Employee $employee) use ($savedRecords, &$totalRencana, &$totalRealisasi): array {
            $saved = $savedRecords->get($employee->id);
            $rencana = $saved?->rencana ?? [];
            $realisasi = $saved?->realisasi ?? [];

            $totalRencana += count($rencana);
            $totalRealisasi += count($realisasi);

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'nip' => $employee->nip,
                'position' => $employee->position,
                'regu' => $employee->regu,
                'rencana' => $rencana,
                'realisasi' => $realisasi,
            ];
        })->all();

        $performance = $targetWorkingDays > 0 ? round(($totalRealisasi / $targetWorkingDays) * 100) : 0;

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

        $pdf = Pdf::loadView('har.jadwal.patrol-check-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'days' => $days,
            'rows' => $rows,
            'targetWorkingDays' => $targetWorkingDays,
            'totalRencana' => $totalRencana,
            'totalRealisasi' => $totalRealisasi,
            'performance' => $performance,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_Patrol_Check_{$safeUnitName}_{$month}_{$year}.pdf");
    }
}
