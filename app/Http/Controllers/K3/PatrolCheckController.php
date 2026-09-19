<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\K3PatrolCheckJadwal;
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
 * Jadwal Patrol Check Harian K3L & Lingkungan (modul K3). Tiap pekerjaan punya
 * dua baris timeline RENC (rencana) & REAL (realisasi); target = jumlah
 * rencana, realisasi = jumlah realisasi, persentase = realisasi ÷ target.
 */
class PatrolCheckController extends Controller
{
    /**
     * @var list<string>
     */
    public const DEFAULT_URAIAN = [
        'Periksa kesiapan pompa oil trap',
        'Bersihkan lantai dan area oil trap',
        'Bersihkan lantai dan area TPS LB3',
        'Periksa dan bersihkan kemasan LB3',
        'Bersihkan lantai dan peralatan pada rumah pompa hydrant',
        'Cek kebersihan dan kelengkapan Kotak P3K',
        'Periksa Kesiapan Pilar dan peralatan pada box hydrant',
        'Bersihkan pilar dan panel peralatan hydrant',
        'Periksa kesiapan dan kebersihan APAR, APAB dan APAT',
        'Bersihkan saluran air hujan dan oil trap dari gedung pembangkit hingga outlet oil trap',
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

        $days = $this->buildDays($year, $month, withMeta: true);

        $savedRecords = K3PatrolCheckJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = collect(self::DEFAULT_URAIAN)->map(fn (string $uraian, int $idx): array => [
                'id' => null,
                'no_urut' => $idx + 1,
                'uraian' => $uraian,
                'rencana' => [],
                'realisasi' => [],
                'bobot_sla' => 0,
                'keterangan' => '',
            ])->all();
        } else {
            $rows = $savedRecords->map(fn (K3PatrolCheckJadwal $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'uraian' => $r->uraian,
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'bobot_sla' => (float) $r->bobot_sla,
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('k3/jadwal/patrol-check/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
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
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.uraian' => ['required', 'string', 'max:500'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.bobot_sla' => ['nullable', 'numeric', 'min:0'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        $cleanDays = fn ($days) => collect($days ?? [])
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 1 && $d <= 31)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year, $cleanDays): void {
            $existingIds = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $attributes = [
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'uraian' => $row['uraian'],
                    'rencana' => $cleanDays($row['rencana'] ?? []),
                    'realisasi' => $cleanDays($row['realisasi'] ?? []),
                    'bobot_sla' => (float) ($row['bobot_sla'] ?? 0),
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3PatrolCheckJadwal::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = K3PatrolCheckJadwal::create([
                    ...$attributes,
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                ]);
                $existingIds[] = $newRecord->id;
            }

            K3PatrolCheckJadwal::query()
                ->where('unit_id', $unitId)
                ->where('year', $year)
                ->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Patrol Check Harian K3L {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Patrol Check Harian K3L berhasil disimpan.',
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

        $days = $this->buildDays($year, $month, withMeta: false);

        $records = K3PatrolCheckJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $source = $records->isEmpty()
            ? collect(self::DEFAULT_URAIAN)->map(fn (string $uraian, int $idx): array => [
                'no_urut' => $idx + 1,
                'uraian' => $uraian,
                'rencana' => [],
                'realisasi' => [],
                'bobot_sla' => 0.0,
                'keterangan' => '',
            ])
            : $records->map(fn (K3PatrolCheckJadwal $r, int $idx): array => [
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'uraian' => $r->uraian,
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'bobot_sla' => (float) $r->bobot_sla,
                'keterangan' => $r->keterangan ?? '',
            ]);

        $rows = $source->map(function (array $r): array {
            $target = count($r['rencana']);
            $realisasi = count($r['realisasi']);
            $r['target'] = $target;
            $r['realisasi_count'] = $realisasi;
            $r['performance'] = $target > 0 ? (int) round(($realisasi / $target) * 100) : 0;

            return $r;
        })->all();

        // Overall weighted SLA = Σ(bobot × persentase) ÷ Σ bobot.
        $totalBobot = collect($rows)->sum('bobot_sla');
        $overallSla = $totalBobot > 0
            ? (int) round(collect($rows)->sum(fn (array $r): float => (float) $r['bobot_sla'] * $r['performance']) / $totalBobot)
            : 0;

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

        $pdf = Pdf::loadView('k3.jadwal.patrol-check-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'days' => $days,
            'rows' => $rows,
            'overallSla' => $overallSla,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_Patrol_Check_K3L_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDays(int $year, int $month, bool $withMeta): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        return collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays, $withMeta): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isRed = $date->isSaturday() || $date->isSunday() || $holiday !== null;

            if (! $withMeta) {
                return ['day' => $day, 'is_red' => $isRed];
            }

            return [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_red' => $isRed,
                'holiday' => $holiday?->description,
            ];
        })->all();
    }
}
