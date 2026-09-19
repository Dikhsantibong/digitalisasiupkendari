<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\K3PekerjaanRutin;
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
 * Jadwal Pekerjaan Rutin K3L & Lingkungan 11 KIT (modul K3).
 * Tiap pekerjaan punya dua baris timeline RENC (rencana) & REAL (realisasi);
 * target = jumlah rencana, real = jumlah realisasi, kinerja = real ÷ target.
 */
class PekerjaanRutinController extends Controller
{
    /**
     * Daftar pekerjaan default sesuai format Jadwal Pekerjaan Rutin K3L & Lingkungan 11 KIT.
     *
     * @var list<string>
     */
    public const DEFAULT_URAIAN = [
        'CEK DAN BERSIHKAN OIL TRAP',
        'CEK DAN BERSIHKAN KANAL',
        'CEK TEMPAT PENIRISAN MAJUN',
        'CEK DAN BERSIHKAN SERTA PEMANASAN AKI DIRUMAH POMPA HIDRAN',
        'CEK DAN BERSIHKAN PILAR DAN PANEL PENYIMPANAN SELANG HIDRANT',
        'CEK DAN BERSIHKAN TPS LB3',
        'CEK DAN ISI ULANG KOTAK P3K',
        'PENGECEKKAN DAN PEMANTAUAN APAR',
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

        $savedRecords = K3PekerjaanRutin::query()
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
                'paraf' => 'Empty',
                'sort_order' => $idx,
            ])->all();
        } else {
            $rows = $savedRecords->map(fn (K3PekerjaanRutin $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'uraian' => $r->uraian,
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'paraf' => $r->paraf ?? 'Empty',
                'sort_order' => $r->sort_order,
            ])->all();
        }

        return Inertia::render('k3/jadwal/pekerjaan-rutin/index', [
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
                'units' => $units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
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

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.uraian' => ['required', 'string', 'max:500'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.rencana.*' => ['integer', 'between:1,31'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.realisasi.*' => ['integer', 'between:1,31'],
            'rows.*.paraf' => ['nullable', 'string', 'max:100'],
            'rows.*.sort_order' => ['nullable', 'integer'],
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $rowsData = $validated['rows'] ?? [];

        DB::transaction(function () use ($unit, $year, $month, $rowsData, $user): void {
            $retainedIds = [];

            foreach ($rowsData as $index => $rowData) {
                $recordId = ! empty($rowData['id']) ? (int) $rowData['id'] : null;

                $data = [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $rowData['no_urut'] ?? ($index + 1),
                    'uraian' => $rowData['uraian'],
                    'rencana' => array_values(array_unique($rowData['rencana'] ?? [])),
                    'realisasi' => array_values(array_unique($rowData['realisasi'] ?? [])),
                    'paraf' => $rowData['paraf'] ?? null,
                    'sort_order' => $rowData['sort_order'] ?? $index,
                    'input_by' => $user->id,
                ];

                if ($recordId !== null) {
                    $existing = K3PekerjaanRutin::query()
                        ->where('unit_id', $unit->id)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->find($recordId);

                    if ($existing) {
                        $existing->update($data);
                        $retainedIds[] = $existing->id;

                        continue;
                    }
                }

                $created = K3PekerjaanRutin::query()->create($data);
                $retainedIds[] = $created->id;
            }

            // Hapus record yang dihapus oleh pengguna dari UI
            K3PekerjaanRutin::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->whereNotIn('id', $retainedIds)
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Pekerjaan Rutin K3L & Lingkungan 11 KIT {$unit->name} periode {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Pekerjaan Rutin K3L & Lingkungan 11 KIT berhasil disimpan.',
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

        $records = K3PekerjaanRutin::query()
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
                'paraf' => 'Empty',
            ])
            : $records->map(fn (K3PekerjaanRutin $r, int $idx): array => [
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'uraian' => $r->uraian,
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'paraf' => $r->paraf ?? 'Empty',
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

        $pdf = Pdf::loadView('k3.jadwal.pekerjaan-rutin-pdf', [
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

        return $pdf->download("Jadwal_Pekerjaan_Rutin_K3L_{$safeUnitName}_{$month}_{$year}.pdf");
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
