<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\OperasiBlackstartJadwal;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BlackstartController extends Controller
{
    private const DEFAULT_ITEMS = [
        'ENGINE DIESEL GENSET (EDG)',
        'SISTEM BATTERY',
        'COMPRESSOR ELEKTRIC',
        'COMPRESSOR DIESEL',
        'TIDAK ADA INSTALASI BLACKSTART',
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

        $savedRecords = OperasiBlackstartJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = collect(self::DEFAULT_ITEMS)->map(function (string $uraian, int $idx): array {
                return [
                    'id' => null,
                    'no_urut' => $idx + 1,
                    'uraian' => $uraian,
                    'pic' => '',
                    'rencana' => [],
                    'realisasi' => [],
                    'keterangan' => '',
                ];
            })->all();
        } else {
            $rows = $savedRecords->map(fn (OperasiBlackstartJadwal $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'uraian' => $r->uraian,
                'pic' => $r->pic ?? '',
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'keterangan' => $r->keterangan ?? '',
            ])->all();
        }

        return Inertia::render('operasi/jadwal/blackstart/index', [
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
            'rows.*.uraian' => ['required', 'string', 'max:255'],
            'rows.*.pic' => ['nullable', 'string', 'max:255'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
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

                $ren = $cleanWeeks($row['rencana'] ?? []);
                $real = $cleanWeeks($row['realisasi'] ?? []);

                if (! empty($row['id'])) {
                    $record = OperasiBlackstartJadwal::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update([
                            'no_urut' => $row['no_urut'] ?? ($index + 1),
                            'uraian' => $row['uraian'],
                            'pic' => $row['pic'] ?? null,
                            'rencana' => $ren,
                            'realisasi' => $real,
                            'keterangan' => $row['keterangan'] ?? null,
                            'sort_order' => $index,
                            'input_by' => $user->id,
                        ]);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = OperasiBlackstartJadwal::create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'uraian' => $row['uraian'],
                    'pic' => $row['pic'] ?? null,
                    'rencana' => $ren,
                    'realisasi' => $real,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
                $existingIds[] = $newRecord->id;
            }

            if (! empty($existingIds)) {
                OperasiBlackstartJadwal::query()
                    ->where('unit_id', $unitId)
                    ->where('year', $year)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Pemeriksaan Instalasi Blackstart {$unit->name} Tahun {$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Pemeriksaan Instalasi Blackstart berhasil disimpan.',
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

        return $pdf->download("Jadwal_Pemeriksaan_Instalasi_Blackstart_{$safeUnitName}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = OperasiBlackstartJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $rows = collect(self::DEFAULT_ITEMS)->map(fn (string $uraian, int $idx) => [
                'no_urut' => $idx + 1,
                'uraian' => $uraian,
                'pic' => '',
                'rencana' => [],
                'realisasi' => [],
                'keterangan' => '',
            ])->all();
        } else {
            $rows = $records->map(fn ($r, $idx) => [
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'uraian' => $r->uraian,
                'pic' => $r->pic ?? '',
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
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
                    if (in_array($key, $row['rencana'])) {
                        $count++;
                    }
                    if (in_array($key, $row['realisasi'])) {
                        $count++;
                    }
                }
                $columnTotals[$key] = $count;
            }
        }

        $grandTotal = array_sum($columnTotals);

        // Data rekap tabel kinerja (Table 2)
        $recap = collect($rows)->map(function (array $r): array {
            $renCount = count($r['rencana'] ?? []);
            $realCount = count($r['realisasi'] ?? []);
            $kinerja = $renCount > 0 ? round(($realCount / $renCount) * 100, 1).'%' : '0%';

            return [
                'no_urut' => $r['no_urut'],
                'uraian' => $r['uraian'],
                'rencana' => $renCount,
                'realisasi' => $realCount,
                'kinerja' => $kinerja,
            ];
        })->all();

        $logos = JadwalPdf::logos();

        return ['operasi.jadwal.blackstart-pdf', [
            'unit' => $unit,
            'year' => $year,
            'months' => self::MONTHS,
            'rows' => $rows,
            'columnTotals' => $columnTotals,
            'grandTotal' => $grandTotal,
            'recap' => $recap,
            'logoLeft' => $logos['logoLeft'],
            'logoRight' => $logos['logoRight'],
        ]];
    }
}
