<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\K3InstruksiKerja;
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

class InstruksiKerjaController extends Controller
{
    /**
     * Default activities, in display order, used to seed the grid the first time
     * a unit/period is opened.
     *
     * @var list<array{kegiatan: string, waktu: string, pic: string, peserta: string}>
     */
    public const DEFAULT_KEGIATAN = [
        ['kegiatan' => 'Safety Briefing, HIRADC dan Sosialisasi K3', 'waktu' => '08:00-08:30', 'pic' => '', 'peserta' => 'seluruh Personel'],
        ['kegiatan' => 'APD dan 5S/5R', 'waktu' => '08:00-08:30', 'pic' => '', 'peserta' => 'seluruh Personel'],
        ['kegiatan' => 'Limbah B3 dan Tumpahan BBM', 'waktu' => '08:00-08:30', 'pic' => '', 'peserta' => 'seluruh Personel'],
        ['kegiatan' => 'LOTO dan Pekerjaan Pemeliharaan', 'waktu' => '08:00-08:30', 'pic' => '', 'peserta' => 'seluruh Personel'],
        ['kegiatan' => 'APAR, P3K, EVAKUASI', 'waktu' => '08:00-03:00', 'pic' => '', 'peserta' => 'seluruh Personel'],
        ['kegiatan' => 'Inspeksi K3L dan Evaluasi', 'waktu' => '08:00-03:00', 'pic' => '', 'peserta' => 'seluruh Personel'],
        ['kegiatan' => 'Cheklist Potensi Bahaya Kebakaran/lap. identifikasi potensi kebakaran', 'waktu' => '08:00-03:00', 'pic' => '', 'peserta' => 'PIC K3L'],
        ['kegiatan' => 'Cheklist air limbah b3 dibawah bordes mesin/panel fedder/film limbah b3 diatas drainage ipal oil trap', 'waktu' => '08:00-03:30', 'pic' => '', 'peserta' => 'PIC K3L'],
        ['kegiatan' => 'Jumat Bersih', 'waktu' => '08:00-10:00', 'pic' => '', 'peserta' => 'seluruh Personel'],
        ['kegiatan' => 'Cheklist P3k', 'waktu' => '08:00-03:00', 'pic' => '', 'peserta' => 'PIC K3L'],
        ['kegiatan' => 'Apar/Apat', 'waktu' => '08:00-08:30', 'pic' => '', 'peserta' => 'PIC K3L'],
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

        $savedRecords = K3InstruksiKerja::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = collect(self::DEFAULT_KEGIATAN)->map(fn (array $item, int $idx): array => [
                'id' => null,
                'no_urut' => $idx + 1,
                'kegiatan' => $item['kegiatan'],
                'waktu' => $item['waktu'],
                'pic' => $item['pic'],
                'peserta' => $item['peserta'],
                'rencana' => [],
                'realisasi' => [],
                'keterangan' => '',
            ])->all();
        } else {
            $rows = $savedRecords->map(fn (K3InstruksiKerja $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'kegiatan' => $r->kegiatan,
                'waktu' => $r->waktu ?? '',
                'pic' => $r->pic ?? '',
                'peserta' => $r->peserta ?? '',
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('k3/jadwal/instruksi-kerja/index', [
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
            'rows.*.kegiatan' => ['required', 'string', 'max:500'],
            'rows.*.waktu' => ['nullable', 'string', 'max:100'],
            'rows.*.pic' => ['nullable', 'string', 'max:100'],
            'rows.*.peserta' => ['nullable', 'string', 'max:100'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
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
                    'kegiatan' => $row['kegiatan'],
                    'waktu' => $row['waktu'] ?? null,
                    'pic' => $row['pic'] ?? null,
                    'peserta' => $row['peserta'] ?? null,
                    'rencana' => $cleanDays($row['rencana'] ?? []),
                    'realisasi' => $cleanDays($row['realisasi'] ?? []),
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3InstruksiKerja::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = K3InstruksiKerja::create([
                    ...$attributes,
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                ]);
                $existingIds[] = $newRecord->id;
            }

            K3InstruksiKerja::query()
                ->where('unit_id', $unitId)
                ->where('year', $year)
                ->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Instruksi Kerja K3L {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Instruksi Kerja K3L berhasil disimpan.',
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

        $records = K3InstruksiKerja::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $source = $records->isEmpty()
            ? collect(self::DEFAULT_KEGIATAN)->map(fn (array $item, int $idx): array => [
                'no_urut' => $idx + 1,
                'kegiatan' => $item['kegiatan'],
                'waktu' => $item['waktu'],
                'pic' => $item['pic'],
                'peserta' => $item['peserta'],
                'rencana' => [],
                'realisasi' => [],
                'keterangan' => '',
            ])
            : $records->map(fn (K3InstruksiKerja $r, int $idx): array => [
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'kegiatan' => $r->kegiatan,
                'waktu' => $r->waktu ?? '',
                'pic' => $r->pic ?? '',
                'peserta' => $r->peserta ?? '',
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
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

        $pdf = Pdf::loadView('k3.jadwal.instruksi-kerja-pdf', [
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

        return $pdf->download("Jadwal_Instruksi_Kerja_K3L_{$safeUnitName}_{$month}_{$year}.pdf");
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
