<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiCommPeralatan;
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

class JadwalCommissioningTestPeralatanController extends Controller
{
    private const DEFAULT_EQUIPMENT = [
        'COMPRESOR 1',
        'COMPRESOR 2',
        'POMPA DRAINASE',
        'SEPARATOR OLI',
        'POMPA FUEL TRANSFER',
    ];

    private const MONTHS = [
        1 => 'JANUARY',
        2 => 'FEBRUARY',
        3 => 'MARCH',
        4 => 'APRIL',
        5 => 'MAY',
        6 => 'JUNE',
        7 => 'JULY',
        8 => 'AUGUST',
        9 => 'SEPTEMBER',
        10 => 'OCTOBER',
        11 => 'NOVEMBER',
        12 => 'DECEMBER',
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
            $machineList = collect(range(1, 5))->map(fn ($i) => ['id' => $i, 'name' => "MESIN #0{$i}"])->all();
        } else {
            $machineList = $machines->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all();
        }

        $savedRecords = OperasiCommPeralatan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = collect(self::DEFAULT_EQUIPMENT)->map(function (string $nama, int $idx): array {
                return [
                    'id' => null,
                    'no_urut' => $idx + 1,
                    'nama_peralatan' => $nama,
                    'beban_50' => [],
                    'beban_75' => [],
                    'beban_100' => [],
                    'keterangan' => '',
                ];
            })->all();
        } else {
            $rows = $savedRecords->map(fn (OperasiCommPeralatan $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'nama_peralatan' => $r->nama_peralatan,
                'beban_50' => $r->beban_50 ?? [],
                'beban_75' => $r->beban_75 ?? [],
                'beban_100' => $r->beban_100 ?? [],
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('operasi/jadwal/commissioning-test-peralatan/index', [
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
            'rows.*.nama_peralatan' => ['required', 'string', 'max:255'],
            'rows.*.beban_50' => ['nullable', 'array'],
            'rows.*.beban_75' => ['nullable', 'array'],
            'rows.*.beban_100' => ['nullable', 'array'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $year): void {
            $existingIds = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $cleanWeeks = function (?array $arr): array {
                    return collect($arr ?? [])
                        ->map(fn ($k) => trim((string) $k))
                        ->filter(fn ($k) => preg_match('/^\d{1,2}-\d$/', $k))
                        ->unique()
                        ->values()
                        ->all();
                };

                $b50 = $cleanWeeks($row['beban_50'] ?? []);
                $b75 = $cleanWeeks($row['beban_75'] ?? []);
                $b100 = $cleanWeeks($row['beban_100'] ?? []);

                if (! empty($row['id'])) {
                    $record = OperasiCommPeralatan::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update([
                            'no_urut' => $row['no_urut'] ?? ($index + 1),
                            'nama_peralatan' => $row['nama_peralatan'],
                            'beban_50' => $b50,
                            'beban_75' => $b75,
                            'beban_100' => $b100,
                            'keterangan' => $row['keterangan'] ?? null,
                            'sort_order' => $index,
                            'input_by' => $user->id,
                        ]);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = OperasiCommPeralatan::create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'nama_peralatan' => $row['nama_peralatan'],
                    'beban_50' => $b50,
                    'beban_75' => $b75,
                    'beban_100' => $b100,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
                $existingIds[] = $newRecord->id;
            }

            if (! empty($existingIds)) {
                OperasiCommPeralatan::query()
                    ->where('unit_id', $unitId)
                    ->where('year', $year)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Commissioning Test Peralatan Pembangkit {$unit->name} Tahun {$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Commissioning Test Peralatan Pembangkit berhasil disimpan.',
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

        [$view, $data] = $this->pdfView($unit, 1, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_Commissioning_Peralatan_{$safeUnitName}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = OperasiCommPeralatan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $rows = collect(self::DEFAULT_EQUIPMENT)->map(fn (string $nama, int $idx) => [
                'no_urut' => $idx + 1,
                'nama_peralatan' => $nama,
                'beban_50' => [],
                'beban_75' => [],
                'beban_100' => [],
                'keterangan' => '',
            ])->all();
        } else {
            $rows = $records->map(fn ($r, $idx) => [
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'nama_peralatan' => $r->nama_peralatan,
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
                    if (in_array($key, $row['beban_50'])) {
                        $count++;
                    }
                    if (in_array($key, $row['beban_75'])) {
                        $count++;
                    }
                    if (in_array($key, $row['beban_100'])) {
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
            $machineList = collect(range(1, 5))->map(fn ($i) => ['id' => $i, 'name' => "MESIN #0{$i}"])->all();
        } else {
            $machineList = $machines->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all();
        }

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['operasi.jadwal.commissioning-test-peralatan-pdf', [
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
