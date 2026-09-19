<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HarJadwalPiketOnCall;
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

class JadwalPiketOnCallController extends Controller
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

        $savedRecords = HarJadwalPiketOnCall::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            // Ambil operator / personil pemeliharaan aktif unit untuk default baris
            $employees = Employee::query()
                ->where('unit_id', $unit->id)
                ->where('is_active', true)
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

            $rows = $employees->map(function (Employee $emp, int $idx): array {
                return [
                    'id' => null,
                    'employee_id' => $emp->id,
                    'nama' => $emp->name,
                    'no_hp' => '',
                    'kategori' => $idx >= 3 ? 'HARLIS' : 'HARMES',
                    'target' => 15,
                    'piket' => [],
                ];
            })->all();
        } else {
            $rows = $savedRecords->map(fn (HarJadwalPiketOnCall $r): array => [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'nama' => $r->nama,
                'no_hp' => $r->no_hp ?? '',
                'kategori' => $r->kategori ?: 'HARMES',
                'target' => $r->target ?: 15,
                'piket' => $r->piket ?? [],
            ])->all();
        }

        return Inertia::render('har/jadwal/piket-on-call/index', [
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
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.employee_id' => ['nullable', 'integer'],
            'rows.*.nama' => ['required', 'string', 'max:255'],
            'rows.*.no_hp' => ['nullable', 'string', 'max:50'],
            'rows.*.kategori' => ['required', 'string', 'max:50'],
            'rows.*.target' => ['required', 'integer', 'min:0'],
            'rows.*.piket' => ['nullable', 'array'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year): void {
            $existingIds = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $piket = collect($row['piket'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '' && (string) $v !== '0')
                    ->all();

                if (! empty($row['id'])) {
                    $record = HarJadwalPiketOnCall::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update([
                            'employee_id' => $row['employee_id'] ?? null,
                            'nama' => $row['nama'],
                            'no_hp' => $row['no_hp'] ?? null,
                            'kategori' => $row['kategori'] ?? 'HARMES',
                            'target' => (int) ($row['target'] ?? 15),
                            'piket' => $piket,
                            'sort_order' => $index,
                            'input_by' => $user->id,
                        ]);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = HarJadwalPiketOnCall::create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                    'employee_id' => $row['employee_id'] ?? null,
                    'nama' => $row['nama'],
                    'no_hp' => $row['no_hp'] ?? null,
                    'kategori' => $row['kategori'] ?? 'HARMES',
                    'target' => (int) ($row['target'] ?? 15),
                    'piket' => $piket,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
                $existingIds[] = $newRecord->id;
            }

            if (! empty($existingIds)) {
                HarJadwalPiketOnCall::query()
                    ->where('unit_id', $unitId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Kegiatan Piket Pemeliharaan (ON CALL) {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Piket Pemeliharaan (ON CALL) berhasil disimpan.',
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

        $records = HarJadwalPiketOnCall::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Group by category (e.g. HARMES, HARLIS)
        $groups = [];
        $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI'];
        $grouped = $records->groupBy('kategori');
        $runningIndex = 1;
        $catIndex = 0;

        foreach ($grouped as $kategoriName => $items) {
            $catRoman = $romanNumerals[$catIndex] ?? (string) ($catIndex + 1);
            $personnel = [];

            foreach ($items as $item) {
                $piket = $item->piket ?? [];
                $realisasi = count($piket);
                $target = (int) ($item->target ?: 15);
                $performance = $target > 0 ? round(($realisasi / $target) * 100) : 0;

                $personnel[] = [
                    'no' => $runningIndex++,
                    'nama' => $item->nama,
                    'no_hp' => $item->no_hp ?? '',
                    'target' => $target,
                    'realisasi' => $realisasi,
                    'performance' => $performance,
                    'piket' => $piket,
                ];
            }

            $groups[] = [
                'roman' => $catRoman,
                'kategori' => $kategoriName,
                'personnel' => $personnel,
            ];
            $catIndex++;
        }

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

        $pdf = Pdf::loadView('har.jadwal.piket-on-call-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'days' => $days,
            'groups' => $groups,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_Piket_OnCall_{$safeUnitName}_{$month}_{$year}.pdf");
    }
}
