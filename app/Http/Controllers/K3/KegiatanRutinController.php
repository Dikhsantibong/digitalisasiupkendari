<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\K3KegiatanRutin;
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

class KegiatanRutinController extends Controller
{
    /**
     * Default activities per group, in display order. Used to seed the grid the
     * first time a unit/period is opened; the user can then edit/add/remove.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_KEGIATAN = [
        'harian' => [
            'Absensi',
            'Daily Meeting / Safety Briefing',
            'Pemeriksaan APD Personil',
            'Inspeksi Area Pembangkit',
            'Pemeriksaan Material & Peralatan K3',
            'Housekeeping Area Kerja',
            'Pemeriksaan Penyimpanan B3',
            'Monitoring Lingkungan',
            'Inspeksi Pekerjaan Berisiko Tinggi',
            'Pemeriksaan Limbah dan Drainase',
            'Cheklist Harian K3L',
            'Input data rutin aplikasi online pembangkit',
            'Permit to work (PTW)',
            'Evaluasi Temuan dan Pelaporan',
        ],
        'mingguan' => [
            '5S 5R',
            'Laporan peralatan, material dan tools K3L KIT',
            'Laporan checklist patrol chek K3L KIT',
            'Laporan peralatan dan APD K3L KIT',
            'Laporan peralatan fire fighting KIT',
        ],
        'bulanan' => [
            'Membuat jadwal kegiatan & piket K3L KIT',
            'Membuat dokumen rambu-rambu K3L KIT',
            'Laporan peralatan dan APD K3L KIT',
            'Laporan pengawasan APD semua bagian KIT',
            'Laporan K3L pembangkit',
            'Pembuatan IK / review IK K3L KIT',
        ],
    ];

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

        [$days, $targetWorkingDays] = $this->buildDays($year, $month, withMeta: true);

        $savedRecords = K3KegiatanRutin::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = [];
            $sort = 0;
            foreach (self::DEFAULT_KEGIATAN as $grup => $items) {
                foreach ($items as $idx => $kegiatan) {
                    $rows[] = [
                        'id' => null,
                        'grup' => $grup,
                        'no_urut' => $idx + 1,
                        'kegiatan' => $kegiatan,
                        'target' => $targetWorkingDays,
                        'realisasi_count' => 0,
                        'jadwal' => [],
                        'keterangan' => '',
                        'sort_order' => $sort++,
                    ];
                }
            }
        } else {
            $rows = $savedRecords->map(fn (K3KegiatanRutin $r): array => [
                'id' => $r->id,
                'grup' => $r->grup,
                'no_urut' => $r->no_urut,
                'kegiatan' => $r->kegiatan,
                'target' => $r->target,
                'realisasi_count' => count($r->jadwal ?? []),
                'jadwal' => $r->jadwal ?? [],
                'keterangan' => $r->keterangan ?? '',
                'sort_order' => $r->sort_order,
            ])->all();
        }

        return Inertia::render('k3/jadwal/kegiatan-rutin/index', [
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
            'rows.*.grup' => ['required', 'string', 'in:harian,mingguan,bulanan'],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.kegiatan' => ['required', 'string', 'max:500'],
            'rows.*.target' => ['required', 'integer', 'min:0'],
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

                $attributes = [
                    'grup' => $row['grup'],
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'kegiatan' => $row['kegiatan'],
                    'target' => (int) ($row['target'] ?? 22),
                    'jadwal' => $jadwal,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3KegiatanRutin::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = K3KegiatanRutin::create([
                    ...$attributes,
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                ]);
                $existingIds[] = $newRecord->id;
            }

            K3KegiatanRutin::query()
                ->where('unit_id', $unitId)
                ->where('year', $year)
                ->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Kegiatan Rutin K3L KIT {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Kegiatan Rutin K3L KIT berhasil disimpan.',
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

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$days, $targetWorkingDays] = $this->buildDays($year, $month, withMeta: false);

        $records = K3KegiatanRutin::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groupMeta = [
            'harian' => ['roman' => 'I', 'label' => 'Harian'],
            'mingguan' => ['roman' => 'II', 'label' => 'Mingguan'],
            'bulanan' => ['roman' => 'III', 'label' => 'Bulanan'],
        ];

        $buildRow = function (string $kegiatan, string $grup, int $noUrut, array $jadwal, int $target, string $keterangan): array {
            $realisasi = count($jadwal);
            $performance = $target > 0 ? (int) round(($realisasi / $target) * 100) : 0;

            return [
                'no_urut' => $noUrut,
                'kegiatan' => $kegiatan,
                'target' => $target,
                'realisasi_count' => $realisasi,
                'performance' => $performance,
                'jadwal' => $jadwal,
                'keterangan' => $keterangan,
            ];
        };

        $groups = [];
        foreach ($groupMeta as $grup => $meta) {
            $items = $records->where('grup', $grup)->values();

            if ($items->isEmpty()) {
                $rows = collect(self::DEFAULT_KEGIATAN[$grup] ?? [])
                    ->map(fn (string $k, int $i): array => $buildRow($k, $grup, $i + 1, [], $targetWorkingDays, ''))
                    ->all();
            } else {
                $rows = $items->map(fn (K3KegiatanRutin $r, int $i): array => $buildRow(
                    $r->kegiatan,
                    $grup,
                    $r->no_urut ?: ($i + 1),
                    $r->jadwal ?? [],
                    (int) ($r->target ?: $targetWorkingDays),
                    $r->keterangan ?? '',
                ))->all();
            }

            $groups[] = ['roman' => $meta['roman'], 'label' => $meta['label'], 'rows' => $rows];
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

        $pdf = Pdf::loadView('k3.jadwal.kegiatan-rutin-pdf', [
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

        return $pdf->download("Jadwal_Kegiatan_Rutin_K3L_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * Build the day cells for the month. When $withMeta is true each day carries
     * weekday/holiday metadata for the screen grid; otherwise a slim shape for PDF.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function buildDays(int $year, int $month, bool $withMeta): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays, $withMeta): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isWeekend = $date->isSaturday() || $date->isSunday();
            $isHoliday = $holiday !== null;
            $isRed = $isWeekend || $isHoliday;

            if (! $withMeta) {
                return ['day' => $day, 'is_red' => $isRed];
            }

            return [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_red' => $isRed,
                'holiday' => $holiday?->description,
            ];
        })->all();

        $targetWorkingDays = collect($days)->where('is_red', false)->count();

        return [$days, $targetWorkingDays];
    }
}
