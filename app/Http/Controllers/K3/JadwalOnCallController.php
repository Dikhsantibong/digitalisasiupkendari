<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\K3JadwalOnCall;
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
 * Jadwal On Call K3L PT Mitra Karya Prima (modul K3).
 * Periode cut-off standar: tanggal 16 bulan sebelumnya hingga 15 bulan berjalan.
 */
class JadwalOnCallController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
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

        $days = $this->buildCutOffDays($year, $month);

        $savedRecords = K3JadwalOnCall::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            // Ambil personil unit (utamakan K3L / Keamanan / Satpam)
            $k3Employees = Employee::query()
                ->where('unit_id', $unit->id)
                ->where('is_active', true)
                ->where(function ($q): void {
                    $q->where('position', 'like', '%k3%')
                        ->orWhere('position', 'like', '%lingkungan%')
                        ->orWhere('position', 'like', '%keamanan%')
                        ->orWhere('position', 'like', '%satpam%')
                        ->orWhere('position', 'like', '%security%');
                })
                ->orderBy('name')
                ->get(['id', 'name', 'nip', 'position']);

            if ($k3Employees->isEmpty()) {
                $k3Employees = Employee::query()
                    ->where('unit_id', $unit->id)
                    ->where('is_active', true)
                    ->where('position', 'not like', '%manager%')
                    ->where('position', 'not like', '%manajer%')
                    ->orderBy('name')
                    ->limit(10)
                    ->get(['id', 'name', 'nip', 'position']);
            }

            $rows = $k3Employees->map(function (Employee $emp, int $idx): array {
                return [
                    'id' => null,
                    'employee_id' => $emp->id,
                    'nama' => $emp->name,
                    'kode_prk' => '',
                    'jabatan' => $emp->position ?? 'Petugas K3L',
                    'schedule' => (object) [],
                    'total' => 0,
                    'nilai' => 100000.0,
                    'rupiah' => 0.0,
                    'sort_order' => $idx,
                ];
            })->all();
        } else {
            $rows = $savedRecords->map(fn (K3JadwalOnCall $r): array => [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'nama' => $r->nama,
                'kode_prk' => $r->kode_prk ?? '',
                'jabatan' => $r->jabatan ?? '',
                'schedule' => $r->schedule ?? (object) [],
                'total' => (int) $r->total,
                'nilai' => (float) $r->nilai,
                'rupiah' => (float) $r->rupiah,
                'sort_order' => $r->sort_order,
            ])->all();
        }

        return Inertia::render('k3/jadwal/on-call/index', [
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
                'employees' => Employee::query()
                    ->where('unit_id', $unit->id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'position'])
                    ->all(),
            ],
            'days' => $days,
            'rows' => $rows,
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.employee_id' => ['nullable', 'integer'],
            'rows.*.nama' => ['required', 'string', 'max:255'],
            'rows.*.kode_prk' => ['nullable', 'string', 'max:100'],
            'rows.*.jabatan' => ['nullable', 'string', 'max:255'],
            'rows.*.schedule' => ['nullable', 'array'],
            'rows.*.total' => ['nullable', 'integer', 'min:0'],
            'rows.*.nilai' => ['nullable', 'numeric', 'min:0'],
            'rows.*.rupiah' => ['nullable', 'numeric', 'min:0'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year): void {
            $existingIds = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $schedule = (array) ($row['schedule'] ?? []);
                // Filter out empty schedule keys if needed
                $filteredSchedule = [];
                foreach ($schedule as $dateKey => $val) {
                    if ($val !== null && trim((string) $val) !== '') {
                        $filteredSchedule[$dateKey] = trim((string) $val);
                    }
                }

                $total = isset($row['total']) && is_numeric($row['total'])
                    ? (int) $row['total']
                    : $this->countActiveOnCall($filteredSchedule);

                $nilai = isset($row['nilai']) && is_numeric($row['nilai']) ? (float) $row['nilai'] : 100000.0;
                $rupiah = isset($row['rupiah']) && is_numeric($row['rupiah']) ? (float) $row['rupiah'] : ($total * $nilai);

                $attributes = [
                    'employee_id' => $row['employee_id'] ?? null,
                    'nama' => $row['nama'],
                    'kode_prk' => $row['kode_prk'] ?? null,
                    'jabatan' => $row['jabatan'] ?? null,
                    'schedule' => $filteredSchedule,
                    'total' => $total,
                    'nilai' => $nilai,
                    'rupiah' => $rupiah,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3JadwalOnCall::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = K3JadwalOnCall::create([
                    ...$attributes,
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                ]);
                $existingIds[] = $newRecord->id;
            }

            K3JadwalOnCall::query()
                ->where('unit_id', $unitId)
                ->where('year', $year)
                ->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal On Call K3L {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal on call K3L berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );

        $unit = Unit::query()->with('serviceUnit')->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $days = $this->buildCutOffDays($year, $month);

        $records = K3JadwalOnCall::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];
        $monthName = $monthNames[$month] ?? '';

        $prevMonthDate = Carbon::create($year, $month, 1)->subMonth();
        $prevMonthName = $monthNames[$prevMonthDate->month] ?? '';
        $prevYear = $prevMonthDate->year;

        $rows = $records->map(fn (K3JadwalOnCall $r, int $idx): array => [
            'no_urut' => $idx + 1,
            'nama' => $r->nama,
            'kode_prk' => $r->kode_prk ?? '',
            'jabatan' => $r->jabatan ?? '',
            'schedule' => $r->schedule ?? [],
            'total' => (int) $r->total,
            'nilai' => (float) $r->nilai,
            'rupiah' => (float) $r->rupiah,
        ])->all();

        $totalNilai = array_sum(array_column($rows, 'nilai'));
        $totalRupiah = array_sum(array_column($rows, 'rupiah'));

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        $pdf = Pdf::loadView('k3.jadwal.on-call-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'prevMonthName' => $prevMonthName,
            'prevYear' => $prevYear,
            'days' => $days,
            'rows' => $rows,
            'totalNilai' => $totalNilai,
            'totalRupiah' => $totalRupiah,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_OnCall_K3L_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * Build cut-off days: from 16th of previous month through 15th of selected month.
     *
     * @return list<array<string, mixed>>
     */
    private function buildCutOffDays(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->subMonth()->day(16);
        $endDate = Carbon::create($year, $month, 15);

        $holidays = Holiday::query()
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get(['date', 'description']);

        $days = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->format('Y-m-d') === $dateStr);
            $isWeekend = $current->isSaturday() || $current->isSunday();
            $isHoliday = $holiday !== null;

            $days[] = [
                'date' => $dateStr,
                'day' => $current->day,
                'month' => $current->month,
                'year' => $current->year,
                'dow' => $current->locale('id')->isoFormat('dd'),
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_red' => $isHoliday,
                'holiday' => $holiday?->description,
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Count active on-call assignments. Exclude 'L', 'OFF', 'CT', 'A', 'KTA', ''
     *
     * @param  array<string, mixed>  $schedule
     */
    private function countActiveOnCall(array $schedule): int
    {
        $inactive = ['L', 'OFF', 'CT', 'A', 'KTA', ''];
        $count = 0;
        foreach ($schedule as $val) {
            $code = strtoupper(trim((string) $val));
            if (! in_array($code, $inactive, true)) {
                $count++;
            }
        }

        return $count;
    }
}
