<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarJadwalP0P5;
use App\Models\Holiday;
use App\Models\Machine;
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

class JadwalP0P5Controller extends Controller
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

        // Ambil mesin aktif khusus unit ini
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'serial_number', 'capacity_kw']);

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

        $savedRecords = HarJadwalP0P5::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('machine_id');

        $rows = $machines->map(function (Machine $machine) use ($savedRecords): array {
            $saved = $savedRecords->get($machine->id);

            return [
                'machine_id' => $machine->id,
                'name' => $machine->name,
                'type' => $machine->type,
                'serial_number' => $machine->serial_number,
                'capacity_kw' => $machine->capacity_kw,
                'rencana' => $saved?->rencana ?? [],
                'realisasi' => $saved?->realisasi ?? [],
                'durasi' => $saved?->durasi ?? [],
                'warna' => $saved?->warna ?? ['rencana' => [], 'realisasi' => []],
                'operating_hours' => $saved?->operating_hours ?? '',
                'keterangan' => $saved?->keterangan ?? '',
            ];
        })->all();

        return Inertia::render('har/jadwal/p0-p5/index', [
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
            'rows.*.machine_id' => ['required', 'integer'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.durasi' => ['nullable', 'array'],
            'rows.*.warna' => ['nullable', 'array'],
            'rows.*.operating_hours' => ['nullable', 'string'],
            'rows.*.keterangan' => ['nullable', 'string'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year): void {
            foreach ($validated['rows'] ?? [] as $row) {
                // Filter out empty entries from day maps
                $rencana = collect($row['rencana'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')
                    ->all();

                $realisasi = collect($row['realisasi'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')
                    ->all();

                $durasi = collect($row['durasi'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')
                    ->all();

                $warna = $row['warna'] ?? [];

                HarJadwalP0P5::query()->updateOrCreate(
                    [
                        'unit_id' => $unitId,
                        'machine_id' => (int) $row['machine_id'],
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'rencana' => $rencana,
                        'realisasi' => $realisasi,
                        'durasi' => $durasi,
                        'warna' => $warna,
                        'operating_hours' => $row['operating_hours'] ?? null,
                        'keterangan' => $row['keterangan'] ?? null,
                        'input_by' => $user->id,
                    ]
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Kegiatan Pemeliharaan P0 - P5 {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Kegiatan Pemeliharaan P0 - P5 berhasil disimpan.',
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

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'serial_number', 'capacity_kw']);

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

        $savedRecords = HarJadwalP0P5::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('machine_id');

        $rows = $machines->map(function (Machine $machine) use ($savedRecords): array {
            $saved = $savedRecords->get($machine->id);

            return [
                'machine_id' => $machine->id,
                'name' => $machine->name,
                'type' => $machine->type,
                'serial_number' => $machine->serial_number,
                'capacity_kw' => $machine->capacity_kw,
                'rencana' => $saved?->rencana ?? [],
                'realisasi' => $saved?->realisasi ?? [],
                'durasi' => $saved?->durasi ?? [],
                'warna' => $saved?->warna ?? ['rencana' => [], 'realisasi' => []],
                'operating_hours' => $saved?->operating_hours ?? '',
                'keterangan' => $saved?->keterangan ?? '',
            ];
        })->all();

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

        $pdf = Pdf::loadView('har.jadwal.p0-p5-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'days' => $days,
            'rows' => $rows,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_P0_P5_{$safeUnitName}_{$month}_{$year}.pdf");
    }
}
