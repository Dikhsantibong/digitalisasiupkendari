<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarJadwalMeetingPemeliharaan;
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

class JadwalMeetingPemeliharaanController extends Controller
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

        $dowMap = [
            1 => 'SN',
            2 => 'SL',
            3 => 'RB',
            4 => 'KM',
            5 => 'JM',
            6 => 'SB',
            7 => 'MG',
        ];

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays, $dowMap): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isWeekend = $date->isSaturday() || $date->isSunday();
            $isHoliday = $holiday !== null;

            return [
                'day' => $day,
                'dow' => $dowMap[$date->dayOfWeekIso] ?? $date->locale('id')->isoFormat('dd'),
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_red' => $isWeekend || $isHoliday,
                'holiday' => $holiday?->description,
            ];
        })->all();

        $savedRecords = HarJadwalMeetingPemeliharaan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = [
                [
                    'id' => null,
                    'uraian' => 'Jadwal Meeting Pemeliharaan',
                    'target' => 1,
                    'rencana' => [],
                    'realisasi' => [],
                    'keterangan' => '',
                ],
            ];
        } else {
            $rows = $savedRecords->map(fn (HarJadwalMeetingPemeliharaan $item): array => [
                'id' => $item->id,
                'uraian' => $item->uraian,
                'target' => $item->target ?? 1,
                'rencana' => $item->rencana ?? [],
                'realisasi' => $item->realisasi ?? [],
                'keterangan' => $item->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('har/jadwal/meeting-pemeliharaan/index', [
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
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.uraian' => ['required', 'string', 'max:255'],
            'rows.*.target' => ['required', 'integer', 'min:0'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.keterangan' => ['nullable', 'string'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year): void {
            $existingIds = [];

            foreach ($validated['rows'] as $index => $rowData) {
                $rencana = collect($rowData['rencana'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '' && (int) $v > 0)
                    ->all();

                $realisasi = collect($rowData['realisasi'] ?? [])
                    ->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '' && (int) $v > 0)
                    ->all();

                $target = (int) ($rowData['target'] ?? 1);

                if (! empty($rowData['id'])) {
                    $record = HarJadwalMeetingPemeliharaan::query()
                        ->where('id', $rowData['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update([
                            'uraian' => $rowData['uraian'],
                            'target' => $target,
                            'rencana' => $rencana,
                            'realisasi' => $realisasi,
                            'keterangan' => $rowData['keterangan'] ?? null,
                            'sort_order' => $index,
                            'input_by' => $user->id,
                        ]);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = HarJadwalMeetingPemeliharaan::create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                    'uraian' => $rowData['uraian'],
                    'target' => $target,
                    'rencana' => $rencana,
                    'realisasi' => $realisasi,
                    'keterangan' => $rowData['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
                $existingIds[] = $newRecord->id;
            }

            // Clean up any removed rows
            if (! empty($existingIds)) {
                HarJadwalMeetingPemeliharaan::query()
                    ->where('unit_id', $unitId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Meeting Pemeliharaan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Meeting Pemeliharaan berhasil disimpan.',
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

        $dowMap = [
            1 => 'SN',
            2 => 'SL',
            3 => 'RB',
            4 => 'KM',
            5 => 'JM',
            6 => 'SB',
            7 => 'MG',
        ];

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays, $dowMap): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isWeekend = $date->isSaturday() || $date->isSunday();
            $isHoliday = $holiday !== null;

            return [
                'day' => $day,
                'dow' => $dowMap[$date->dayOfWeekIso] ?? $date->locale('id')->isoFormat('dd'),
                'is_red' => $isWeekend || $isHoliday,
            ];
        })->all();

        $records = HarJadwalMeetingPemeliharaan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $rows = [
                [
                    'uraian' => 'Jadwal Meeting Pemeliharaan',
                    'target' => 1,
                    'rencana' => [],
                    'realisasi' => [],
                    'total_rencana' => 0,
                    'total_realisasi' => 0,
                    'performance' => 0,
                ],
            ];
        } else {
            $rows = $records->map(function (HarJadwalMeetingPemeliharaan $item): array {
                $rencana = $item->rencana ?? [];
                $realisasi = $item->realisasi ?? [];
                $totalRencana = array_sum(array_map('intval', $rencana));
                $totalRealisasi = array_sum(array_map('intval', $realisasi));
                $target = (int) ($item->target ?: 1);
                $performance = $target > 0 ? round(($totalRealisasi / $target) * 100) : 0;

                return [
                    'uraian' => $item->uraian,
                    'target' => $target,
                    'rencana' => $rencana,
                    'realisasi' => $realisasi,
                    'total_rencana' => $totalRencana,
                    'total_realisasi' => $totalRealisasi,
                    'performance' => $performance,
                ];
            })->all();
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

        $pdf = Pdf::loadView('har.jadwal.meeting-pemeliharaan-pdf', [
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

        return $pdf->download("Jadwal_Meeting_Pemeliharaan_{$safeUnitName}_{$month}_{$year}.pdf");
    }
}
