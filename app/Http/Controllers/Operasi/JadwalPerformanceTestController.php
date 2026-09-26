<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiPerformanceTestMesin;
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

class JadwalPerformanceTestController extends Controller
{
    private const DEFAULT_MESINS = [
        'MESIN#01 / CUMM #6',
        'MESIN#02 / CUMM #7',
        'MESIN#03 / CUMM #8',
    ];

    private const MONTHS = [
        'JANUARY',
        'FEBRUARY',
        'MARCH',
        'APRIL',
        'MAY',
        'JUNE',
        'JULY',
        'AUGUST',
        'SEPTEMBER',
        'OCTOBER',
        'NOVEMBER',
        'DECEMBER',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);

        // Mesin aktif unit untuk tabel rekap (Table 2)
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($machines->isEmpty()) {
            $machineList = collect(range(1, 3))->map(fn ($i) => ['id' => $i, 'name' => "MESIN #0{$i}"])->all();
        } else {
            $machineList = $machines->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all();
        }

        $savedRecords = OperasiPerformanceTestMesin::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $sampleMarks = [
                0 => ['1-1', '4-4', '7-1'],
                1 => ['2-1', '5-3', '8-3'],
                2 => ['3-3', '6-3'],
            ];

            $rows = collect(self::DEFAULT_MESINS)->map(function (string $nama, int $idx) use ($sampleMarks): array {
                $marks = $sampleMarks[$idx] ?? [];

                return [
                    'id' => null,
                    'no_urut' => $idx + 1,
                    'nama_mesin' => $nama,
                    'section' => 'A. PEMBUATAN DATA TEKNIKS',
                    'beban_50' => $marks,
                    'beban_75' => $marks,
                    'beban_100' => $marks,
                    'keterangan' => '',
                ];
            })->all();
        } else {
            $rows = $savedRecords->map(fn (OperasiPerformanceTestMesin $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'nama_mesin' => $r->nama_mesin,
                'section' => $r->section ?? 'A. PEMBUATAN DATA TEKNIKS',
                'beban_50' => $r->beban_50 ?? [],
                'beban_75' => $r->beban_75 ?? [],
                'beban_100' => $r->beban_100 ?? [],
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('operasi/jadwal/performance-test/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'year' => $year,
            ],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'machines' => $machineList,
            'rows' => $rows,
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.nama_mesin' => ['required', 'string', 'max:255'],
            'rows.*.section' => ['nullable', 'string', 'max:255'],
            'rows.*.beban_50' => ['nullable', 'array'],
            'rows.*.beban_75' => ['nullable', 'array'],
            'rows.*.beban_100' => ['nullable', 'array'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $year = (int) $validated['year'];
        $cleanArray = fn ($arr) => is_array($arr)
            ? array_values(array_filter($arr, fn ($v) => is_string($v) && trim($v) !== ''))
            : [];

        DB::transaction(function () use ($validated, $unit, $year, $user, $cleanArray): void {
            OperasiPerformanceTestMesin::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->delete();

            foreach ($validated['rows'] ?? [] as $index => $row) {
                if (trim((string) ($row['nama_mesin'] ?? '')) === '') {
                    continue;
                }

                OperasiPerformanceTestMesin::create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'nama_mesin' => $row['nama_mesin'],
                    'section' => $row['section'] ?? 'A. PEMBUATAN DATA TEKNIKS',
                    'beban_50' => $cleanArray($row['beban_50'] ?? []),
                    'beban_75' => $cleanArray($row['beban_75'] ?? []),
                    'beban_100' => $cleanArray($row['beban_100'] ?? []),
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Pelaksanaan Performance Test Mesin {$unit->name} {$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Pelaksanaan Performance Test Mesin berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiInputView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);

        [$view, $data] = $this->pdfView($unit, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_Performance_Test_Mesin_{$safeUnitName}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & year.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $year): array
    {
        $records = OperasiPerformanceTestMesin::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $sampleMarks = [
                0 => ['1-1', '4-4', '7-1'],
                1 => ['2-1', '5-3', '8-3'],
                2 => ['3-3', '6-3'],
            ];

            $rows = collect(self::DEFAULT_MESINS)->map(fn (string $nama, int $idx) => [
                'no_urut' => $idx + 1,
                'nama_mesin' => $nama,
                'section' => 'A. PEMBUATAN DATA TEKNIKS',
                'beban_50' => $sampleMarks[$idx] ?? [],
                'beban_75' => $sampleMarks[$idx] ?? [],
                'beban_100' => $sampleMarks[$idx] ?? [],
                'keterangan' => '',
            ])->all();
        } else {
            $rows = $records->map(fn ($r, $idx) => [
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'nama_mesin' => $r->nama_mesin,
                'section' => $r->section ?? 'A. PEMBUATAN DATA TEKNIKS',
                'beban_50' => $r->beban_50 ?? [],
                'beban_75' => $r->beban_75 ?? [],
                'beban_100' => $r->beban_100 ?? [],
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        // Hitung total kolom per minggu (12 bulan x 4 minggu)
        $columnTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            for ($w = 1; $w <= 4; $w++) {
                $key = "{$m}-{$w}";
                $count = 0;
                foreach ($rows as $row) {
                    if (in_array($key, (array) ($row['beban_50'] ?? []))) {
                        $count++;
                    }
                    if (in_array($key, (array) ($row['beban_75'] ?? []))) {
                        $count++;
                    }
                    if (in_array($key, (array) ($row['beban_100'] ?? []))) {
                        $count++;
                    }
                }
                $columnTotals[$key] = $count;
            }
        }

        $grandTotal = array_sum($columnTotals);

        // Mesin aktif unit untuk tabel rekap (Table 2)
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($machines->isEmpty()) {
            $machineList = collect(range(1, 3))->map(fn ($i) => ['id' => $i, 'name' => "MESIN #0{$i}"])->all();
        } else {
            $machineList = $machines->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all();
        }

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['operasi.jadwal.performance-test-pdf', [
            'unit' => $unit,
            'year' => $year,
            'months' => self::MONTHS,
            'rows' => $rows,
            'columnTotals' => $columnTotals,
            'grandTotal' => $grandTotal,
            'machines' => $machineList,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }
}
