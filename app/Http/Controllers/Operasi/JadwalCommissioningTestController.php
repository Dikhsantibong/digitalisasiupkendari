<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OperasiCommissioningTest;
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

class JadwalCommissioningTestController extends Controller
{
    private const DEFAULT_SECTIONS = [
        [
            'section' => 'PERSIAPAN',
            'items' => [
                'Periksa dan pastikan semua PMT Feeder, Generator dan coupling dalam posisi OPEN',
                'Pastikan Kompresor diesel dan elektrik dlaam posisi standby',
                'Periksa dan pastikan saklar alat bantu semua mesin pada posisi OFF atau AUTO',
                'Pastikan kondisi PMT Trafo dan PMT Generator dalam kondisi OPEN',
                'Pastikan Tabung udara terisi dan cukup untuk BLACK START mesin Daihatsu 1.',
                'Pastikan sistem penunjang Mesin Daihatsu kondisi siap dioperasikan',
                'Operasikan salah satu mesin Daihatsu untuk suplai PS',
                'Pastikan parameter operasi mesin Daihatsu (suplay PS) dalam kondisi aman',
            ],
        ],
        [
            'section' => 'PARAREL GENERATOR',
            'items' => [
                'Operasikan salah satu mesin MaK',
                'Tegangan nominal generator terpenuhi dan bisa diatur melalui voltage regulator',
                'Pararel generator',
                'Atur beban dasar',
                'Atur cos phi',
                'UPB Kendari Koordinasi dengan piket GI Kolaka agar ON PMT salah satu Feeder',
            ],
        ],
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
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        // Personil unit untuk rekomendasi PIC
        $employees = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $savedRecords = OperasiCommissioningTest::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $catatan = 'disesuaikan dengan kondisi peralatan unit/sentral kit';

        if ($savedRecords->isEmpty()) {
            $rows = [];
            $runningIdx = 0;
            foreach (self::DEFAULT_SECTIONS as $sec) {
                foreach ($sec['items'] as $itemIdx => $kegiatan) {
                    $rows[] = [
                        'id' => null,
                        'section' => $sec['section'],
                        'no_urut' => $itemIdx + 1,
                        'kegiatan' => $kegiatan,
                        'status' => null,
                        'pic' => '',
                        'paraf' => '',
                    ];
                    $runningIdx++;
                }
            }
        } else {
            $firstWithCatatan = $savedRecords->first(fn ($r) => ! empty($r->catatan));
            if ($firstWithCatatan) {
                $catatan = $firstWithCatatan->catatan;
            }

            $rows = $savedRecords->map(fn (OperasiCommissioningTest $r): array => [
                'id' => $r->id,
                'section' => $r->section,
                'no_urut' => $r->no_urut,
                'kegiatan' => $r->kegiatan,
                'status' => $r->status,
                'pic' => $r->pic ?? '',
                'paraf' => $r->paraf ?? '',
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
                'month' => $month,
                'year' => $year,
            ],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'employees' => $employees,
            'rows' => $rows,
            'catatan' => $catatan,
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
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.section' => ['required', 'string', 'max:100'],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.kegiatan' => ['required', 'string', 'max:500'],
            'rows.*.status' => ['nullable', 'string', 'max:50'],
            'rows.*.pic' => ['nullable', 'string', 'max:255'],
            'rows.*.paraf' => ['nullable', 'string', 'max:50'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $catatan = $validated['catatan'] ?? 'disesuaikan dengan kondisi peralatan unit/sentral kit';

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year, $catatan): void {
            $existingIds = [];

            foreach ($validated['rows'] ?? [] as $index => $row) {
                if (! empty($row['id'])) {
                    $record = OperasiCommissioningTest::query()
                        ->where('id', $row['id'])
                        ->where('unit_id', $unitId)
                        ->first();

                    if ($record) {
                        $record->update([
                            'section' => $row['section'],
                            'no_urut' => $row['no_urut'] ?? ($index + 1),
                            'kegiatan' => $row['kegiatan'],
                            'status' => $row['status'] ?? null,
                            'pic' => $row['pic'] ?? null,
                            'paraf' => $row['paraf'] ?? null,
                            'catatan' => $catatan,
                            'sort_order' => $index,
                            'input_by' => $user->id,
                        ]);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = OperasiCommissioningTest::create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                    'section' => $row['section'],
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'kegiatan' => $row['kegiatan'],
                    'status' => $row['status'] ?? null,
                    'pic' => $row['pic'] ?? null,
                    'paraf' => $row['paraf'] ?? null,
                    'catatan' => $catatan,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ]);
                $existingIds[] = $newRecord->id;
            }

            if (! empty($existingIds)) {
                OperasiCommissioningTest::query()
                    ->where('unit_id', $unitId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Pelaksanaan Commissioning Test Mesin Pembangkit {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Pelaksanaan Commissioning Test berhasil disimpan.',
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
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Jadwal_Commissioning_Test_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = OperasiCommissioningTest::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $catatan = 'disesuaikan dengan kondisi peralatan unit/sentral kit';

        if ($records->isEmpty()) {
            $sections = [];
            foreach (self::DEFAULT_SECTIONS as $sec) {
                $items = [];
                foreach ($sec['items'] as $idx => $kegiatan) {
                    $items[] = [
                        'no_urut' => $idx + 1,
                        'kegiatan' => $kegiatan,
                        'status' => null,
                        'pic' => '',
                        'paraf' => '',
                    ];
                }
                $sections[] = [
                    'section' => $sec['section'],
                    'items' => $items,
                ];
            }
        } else {
            $firstWithCatatan = $records->first(fn ($r) => ! empty($r->catatan));
            if ($firstWithCatatan) {
                $catatan = $firstWithCatatan->catatan;
            }

            $grouped = $records->groupBy('section');
            $sections = [];
            foreach ($grouped as $secTitle => $groupRecords) {
                $sections[] = [
                    'section' => $secTitle,
                    'items' => $groupRecords->map(fn ($r) => [
                        'no_urut' => $r->no_urut,
                        'kegiatan' => $r->kegiatan,
                        'status' => $r->status,
                        'pic' => $r->pic ?? '',
                        'paraf' => $r->paraf ?? '',
                    ])->all(),
                ];
            }
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

        return ['operasi.jadwal.commissioning-test-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'sections' => $sections,
            'catatan' => $catatan,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }
}
