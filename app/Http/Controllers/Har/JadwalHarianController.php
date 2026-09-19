<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarJadwalHarian;
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

class JadwalHarianController extends Controller
{
    private const DEFAULT_KEGIATAN = [
        'Absensi',
        'Daily Meeting / Safety Breefing',
        'Pengecekan kebocoran air, oli, bbm & udara',
        'Pengecekan terminasi battery & pengukuran tegangan battery',
        'Pengecekan sensor & terminasi socket pada sensor',
        'Pengecekan level oli dan air pendingin',
        'Pengukuran asap cerobong dan breather pada mesin yang operasi',
        'Pengecekan vibrasi pada mesin yang operasi',
        'Pengecekan parameter pada panel PCC 3300',
        'Pengecekan terminasi pada panel GCP',
        'Pembersihan panel GCP & body mesin',
        'Analisis Data dan Pelaporan',
    ];

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

        $targetWorkingDays = collect($days)->where('is_red', false)->count();

        $savedRecords = HarJadwalHarian::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = collect(self::DEFAULT_KEGIATAN)->map(function (string $kegiatan, int $idx) use ($targetWorkingDays): array {
                return [
                    'id' => null,
                    'no_urut' => $idx + 1,
                    'kegiatan' => $kegiatan,
                    'target' => $targetWorkingDays,
                    'rencana_count' => $targetWorkingDays,
                    'realisasi_count' => 0,
                    'jadwal' => [],
                    'keterangan' => '',
                ];
            })->all();
        } else {
            $rows = $savedRecords->map(fn (HarJadwalHarian $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'kegiatan' => $r->kegiatan,
                'target' => $r->target,
                'rencana_count' => $r->rencana_count,
                'realisasi_count' => count($r->jadwal ?? []),
                'jadwal' => $r->jadwal ?? [],
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('har/jadwal/harian/index', [
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
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.kegiatan' => ['required', 'string', 'max:500'],
            'rows.*.target' => ['required', 'integer', 'min:0'],
            'rows.*.rencana_count' => ['required', 'integer', 'min:0'],
            'rows.*.jadwal' => ['nullable', 'array'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year): void {
            $existingIds = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $jadwal = collect($row['jadwal'] ?? [])
                    ->map(fn ($d) => (int) $d)
                    ->filter(fn ($d) => $d >= 1 && $d <= 31)
                    ->unique()
                    ->values()
                    ->all();

                $realisasiCount = count($jadwal);

                if (! empty($row['id'])) {
                    $record = HarJadwalHarian::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update([
                            'no_urut' => $row['no_urut'] ?? ($index + 1),
                            'kegiatan' => $row['kegiatan'],
                            'target' => (int) ($row['target'] ?? 20),
                            'rencana_count' => (int) ($row['rencana_count'] ?? 20),
                            'realisasi_count' => $realisasiCount,
                            'jadwal' => $jadwal,
                            'keterangan' => $row['keterangan'] ?? null,
                            'sort_order' => $index,
                            'input_by' => $user->id,
                        ]);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = HarJadwalHarian::create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'kegiatan' => $row['kegiatan'],
                    'target' => (int) ($row['target'] ?? 20),
                    'rencana_count' => (int) ($row['rencana_count'] ?? 20),
                    'realisasi_count' => $realisasiCount,
                    'jadwal' => $jadwal,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
                $existingIds[] = $newRecord->id;
            }

            if (! empty($existingIds)) {
                HarJadwalHarian::query()
                    ->where('unit_id', $unitId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Kegiatan Harian Pemeliharaan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Kegiatan Harian Pemeliharaan berhasil disimpan.',
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

        $targetWorkingDays = collect($days)->where('is_red', false)->count();

        $records = HarJadwalHarian::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $rows = collect(self::DEFAULT_KEGIATAN)->map(function (string $kegiatan, int $idx) use ($targetWorkingDays): array {
                return [
                    'no_urut' => $idx + 1,
                    'kegiatan' => $kegiatan,
                    'target' => $targetWorkingDays,
                    'rencana_count' => $targetWorkingDays,
                    'realisasi_count' => 0,
                    'performance' => 0,
                    'jadwal' => [],
                    'keterangan' => '',
                ];
            })->all();
        } else {
            $rows = $records->map(function (HarJadwalHarian $r, int $idx): array {
                $jadwal = $r->jadwal ?? [];
                $realisasi = count($jadwal);
                $target = (int) ($r->target ?: 20);
                $performance = $target > 0 ? round(($realisasi / $target) * 100) : 0;

                return [
                    'no_urut' => $r->no_urut ?: ($idx + 1),
                    'kegiatan' => $r->kegiatan,
                    'target' => $target,
                    'rencana_count' => (int) ($r->rencana_count ?: 20),
                    'realisasi_count' => $realisasi,
                    'performance' => $performance,
                    'jadwal' => $jadwal,
                    'keterangan' => $r->keterangan ?? '',
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

        $pdf = Pdf::loadView('har.jadwal.harian-pdf', [
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

        return $pdf->download("Jadwal_Kegiatan_Harian_{$safeUnitName}_{$month}_{$year}.pdf");
    }
}
