<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiCommissioningTest;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JadwalCommissioningTestController extends Controller
{
    private const DEFAULT_SECTION = 'A. PEMBUATAN DATA TEKNIKS';

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

        $machineList = $this->machinesOf($unit)->map(fn (Machine $m): array => [
            'id' => $m->id,
            'name' => mb_strtoupper($m->name),
        ])->all();

        $savedRecords = OperasiCommissioningTest::query()
            ->with('machine:id,name')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = array_map(fn (array $row): array => ['id' => null, ...$row], $this->rowsFromMaster($unit));
        } else {
            $rows = $savedRecords->map(fn (OperasiCommissioningTest $r, int $idx): array => [
                'id' => $r->id,
                'machine_id' => $r->machine_id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'nama_mesin' => $r->displayMachineName(),
                'section' => $r->section ?: self::DEFAULT_SECTION,
                'beban_50' => array_values((array) ($r->beban_50 ?? [])),
                'beban_75' => array_values((array) ($r->beban_75 ?? [])),
                'beban_100' => array_values((array) ($r->beban_100 ?? [])),
                'realisasi' => array_values((array) ($r->realisasi ?? [])),
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('operasi/jadwal/commissioning-test/index', [
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
            'rows.*.machine_id' => [
                'nullable',
                'integer',
                Rule::exists('machines', 'id')->where('unit_id', $unit->id),
            ],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.nama_mesin' => ['required', 'string', 'max:255'],
            'rows.*.section' => ['nullable', 'string', 'max:255'],
            'rows.*.beban_50' => ['nullable', 'array'],
            'rows.*.beban_50.*' => ['string', 'regex:/^(?:[1-9]|1[0-2])-[1-4]$/'],
            'rows.*.beban_75' => ['nullable', 'array'],
            'rows.*.beban_75.*' => ['string', 'regex:/^(?:[1-9]|1[0-2])-[1-4]$/'],
            'rows.*.beban_100' => ['nullable', 'array'],
            'rows.*.beban_100.*' => ['string', 'regex:/^(?:[1-9]|1[0-2])-[1-4]$/'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.realisasi.*' => ['string', 'regex:/^(?:[1-9]|1[0-2])-[1-4]$/'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $year = (int) $validated['year'];
        $cleanWeeks = static fn ($arr): array => is_array($arr)
            ? array_values(array_unique(array_filter($arr, static fn ($v): bool => is_string($v) && trim($v) !== '')))
            : [];

        $machineNames = Machine::query()
            ->where('unit_id', $unit->id)
            ->pluck('name', 'id')
            ->map(fn (string $name): string => mb_strtoupper($name))
            ->all();

        DB::transaction(function () use ($validated, $unit, $year, $user, $cleanWeeks, $machineNames): void {
            OperasiCommissioningTest::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->delete();

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $machineId = ! empty($row['machine_id']) ? (int) $row['machine_id'] : null;
                $namaMesin = $machineId && isset($machineNames[$machineId])
                    ? $machineNames[$machineId]
                    : mb_strtoupper(trim((string) ($row['nama_mesin'] ?? '')));

                if ($namaMesin === '') {
                    continue;
                }

                OperasiCommissioningTest::create([
                    'unit_id' => $unit->id,
                    'machine_id' => $machineId,
                    'year' => $year,
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'nama_mesin' => $namaMesin,
                    'section' => trim((string) ($row['section'] ?? '')) ?: self::DEFAULT_SECTION,
                    'beban_50' => $cleanWeeks($row['beban_50'] ?? []),
                    'beban_75' => $cleanWeeks($row['beban_75'] ?? []),
                    'beban_100' => $cleanWeeks($row['beban_100'] ?? []),
                    'realisasi' => $cleanWeeks($row['realisasi'] ?? []),
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Pelaksanaan Commissioning Test Mesin {$unit->name} {$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Pelaksanaan Commissioning Test Mesin berhasil disimpan.',
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

        return $pdf->download("Jadwal_Commissioning_Test_Mesin_{$safeUnitName}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & year — shared by PDF export
     * and the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $monthOrYear, ?int $year = null): array
    {
        $resolvedYear = $year ?? $monthOrYear;

        $records = OperasiCommissioningTest::query()
            ->with('machine:id,name')
            ->where('unit_id', $unit->id)
            ->where('year', $resolvedYear)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $rows = $this->rowsFromMaster($unit);
        } else {
            $rows = $records->map(fn (OperasiCommissioningTest $r, int $idx): array => [
                'machine_id' => $r->machine_id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'nama_mesin' => $r->displayMachineName(),
                'section' => $r->section ?: self::DEFAULT_SECTION,
                'beban_50' => array_values((array) ($r->beban_50 ?? [])),
                'beban_75' => array_values((array) ($r->beban_75 ?? [])),
                'beban_100' => array_values((array) ($r->beban_100 ?? [])),
                'realisasi' => array_values((array) ($r->realisasi ?? [])),
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        $columnTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            for ($w = 1; $w <= 4; $w++) {
                $key = "{$m}-{$w}";
                $count = 0;
                foreach ($rows as $row) {
                    if (in_array($key, (array) ($row['beban_50'] ?? []), true)) {
                        $count++;
                    }
                    if (in_array($key, (array) ($row['beban_75'] ?? []), true)) {
                        $count++;
                    }
                    if (in_array($key, (array) ($row['beban_100'] ?? []), true)) {
                        $count++;
                    }
                }
                $columnTotals[$key] = $count;
            }
        }

        $grandTotal = array_sum($columnTotals);

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['operasi.jadwal.commissioning-test-pdf', [
            'unit' => $unit,
            'year' => $resolvedYear,
            'months' => self::MONTHS,
            'rows' => $rows,
            'has_saved' => $records->isNotEmpty(),
            'columnTotals' => $columnTotals,
            'grandTotal' => $grandTotal,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }

    /**
     * Active generating machines for the unit from Master Data Mesin.
     *
     * @return Collection<int, Machine>
     */
    private function machinesOf(Unit $unit): Collection
    {
        return Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Default rows pulled automatically from Master Data Mesin for unsaved unit-year schedules.
     *
     * @return list<array{machine_id: int, no_urut: int, nama_mesin: string, section: string, beban_50: list<string>, beban_75: list<string>, beban_100: list<string>, realisasi: list<string>, keterangan: string}>
     */
    private function rowsFromMaster(Unit $unit): array
    {
        return $this->machinesOf($unit)->values()->map(fn (Machine $machine, int $idx): array => [
            'machine_id' => $machine->id,
            'no_urut' => $idx + 1,
            'nama_mesin' => mb_strtoupper($machine->name),
            'section' => self::DEFAULT_SECTION,
            'beban_50' => [],
            'beban_75' => [],
            'beban_100' => [],
            'realisasi' => [],
            'keterangan' => '',
        ])->all();
    }
}
