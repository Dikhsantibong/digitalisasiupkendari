<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\PdmJadwalHarian;
use App\Models\PdmJadwalMeta;
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
    private const DEFAULT_STRUCTURE = [
        [
            'kategori' => 'I. MESIN',
            'header' => [
                'no_urut' => 'I',
                'kegiatan' => 'MESIN',
            ],
            'items' => [
                'Absensi',
                'Daily Meeting / Safety Breefing',
                'Pengukuran Kualitas Air',
                'Pengukuran Kualitas Pelumas',
                'Pengukuran Vibrasi',
                'Pengukuran Tekanan Pembakaran',
                'Pengukuran Arus Elmot',
                'Pengukuran Tegangan Baterai',
                'Patrol chek Pdm pembangkit',
                'Input data rutin aplikasi online pembangkit',
                'Permit to work (PTW)',
                'Analisis Data dan Pelaporan',
            ],
        ],
        [
            'kategori' => 'II. Mingguan',
            'header' => [
                'no_urut' => 'II',
                'kegiatan' => 'Mingguan',
            ],
            'items' => [
                '5S 5R',
                'Laporan peralatan, material dan tools PdM KIT',
                'Laporan cheklist Patrol chek PdM KIT',
                'Laporan hasil pemeriksaan Pdm KIT',
                'Laporan dan pengiriman sampel Pdm KIT',
            ],
        ],
        [
            'kategori' => 'III. Bulanan',
            'header' => [
                'no_urut' => 'III',
                'kegiatan' => 'Bulanan',
            ],
            'items' => [
                'Laporan Pdm KIT Pembangkit',
                'Membuat jadwal kegiatan & piket Pdm KIT',
                'Laporan Matlev Pdm KIT',
                'Pembuatan IK & review IK Pdm pembangkit',
            ],
        ],
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::PdmInputView) ||
            $user->hasPermissionTo(PermissionName::PdmLaporanView),
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
            $isSunday = $date->isSunday();
            $isSaturday = $date->isSaturday();
            $isHoliday = $holiday !== null;

            return [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_sunday' => $isSunday,
                'is_weekend' => $isSaturday || $isSunday,
                'is_holiday' => $isHoliday,
                'is_red' => $isSaturday || $isSunday || $isHoliday,
                'holiday' => $holiday?->description,
            ];
        })->all();

        $targetWorkingDays = collect($days)->where('is_red', false)->count();
        if ($targetWorkingDays === 0) {
            $targetWorkingDays = 23;
        }

        $meta = PdmJadwalMeta::query()->firstOrNew([
            'unit_id' => $unit->id,
            'type' => 'harian',
            'year' => $year,
            'month' => $month,
        ]);

        if (! $meta->exists) {
            $meta->doc_number = $meta->doc_number ?: 'PLN-NP-UPKDR/JADWAL-PDM-MATLEV';
            $meta->revision = $meta->revision ?: '00';
            $meta->effective_date = $meta->effective_date ?: Carbon::create($year, $month, 1)->locale('id')->isoFormat('D MMMM Y');
            $meta->page_number = $meta->page_number ?: '1 dari 1';
            $meta->disetujui_nama = $meta->disetujui_nama ?: 'TL PdM & MATLEV';
            $meta->disetujui_jabatan = $meta->disetujui_jabatan ?: 'Team Leader PdM & Matlev';
            $meta->dibuat_nama = $meta->dibuat_nama ?: 'Pelaksana PdM';
            $meta->dibuat_jabatan = $meta->dibuat_jabatan ?: 'Junior Engineer PdM';
        }

        $savedRecords = PdmJadwalHarian::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = [];
            $sortOrder = 0;

            foreach (self::DEFAULT_STRUCTURE as $group) {
                // Add category header row (e.g. I. MESIN)
                $rows[] = [
                    'id' => null,
                    'kategori' => $group['kategori'],
                    'is_category_header' => true,
                    'no_urut' => $group['header']['no_urut'],
                    'kegiatan' => $group['header']['kegiatan'],
                    'target' => $targetWorkingDays,
                    'rencana_count' => $targetWorkingDays,
                    'realisasi_count' => 0,
                    'jadwal' => [],
                    'keterangan' => '',
                    'sort_order' => $sortOrder++,
                ];

                // Add child items
                foreach ($group['items'] as $itemIdx => $itemName) {
                    $rows[] = [
                        'id' => null,
                        'kategori' => $group['kategori'],
                        'is_category_header' => false,
                        'no_urut' => (string) ($itemIdx + 1),
                        'kegiatan' => $itemName,
                        'target' => $targetWorkingDays,
                        'rencana_count' => $targetWorkingDays,
                        'realisasi_count' => 0,
                        'jadwal' => [],
                        'keterangan' => '',
                        'sort_order' => $sortOrder++,
                    ];
                }
            }
        } else {
            $rows = $savedRecords->map(fn (PdmJadwalHarian $r, int $idx): array => [
                'id' => $r->id,
                'kategori' => $r->kategori,
                'is_category_header' => (bool) $r->is_category_header,
                'no_urut' => $r->no_urut ?? (string) ($idx + 1),
                'kegiatan' => $r->kegiatan,
                'target' => $r->target,
                'rencana_count' => $r->rencana_count,
                'realisasi_count' => count($r->jadwal ?? []),
                'jadwal' => $r->jadwal ?? [],
                'keterangan' => $r->keterangan ?? '',
                'sort_order' => $r->sort_order,
            ])->all();
        }

        $monthName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM');

        return Inertia::render('pdm/jadwal/harian/index', [
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
            'days_in_month' => $daysInMonth,
            'month_name' => $monthName,
            'target_working_days' => $targetWorkingDays,
            'rows' => $rows,
            'meta' => [
                'doc_number' => $meta->doc_number,
                'revision' => $meta->revision,
                'effective_date' => $meta->effective_date,
                'page_number' => $meta->page_number,
                'disetujui_nama' => $meta->disetujui_nama,
                'disetujui_jabatan' => $meta->disetujui_jabatan,
                'dibuat_nama' => $meta->dibuat_nama,
                'dibuat_jabatan' => $meta->dibuat_jabatan,
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::PdmInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::PdmInputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'meta' => ['nullable', 'array'],
            'meta.doc_number' => ['nullable', 'string', 'max:100'],
            'meta.revision' => ['nullable', 'string', 'max:50'],
            'meta.effective_date' => ['nullable', 'string', 'max:50'],
            'meta.page_number' => ['nullable', 'string', 'max:50'],
            'meta.disetujui_nama' => ['nullable', 'string', 'max:150'],
            'meta.disetujui_jabatan' => ['nullable', 'string', 'max:150'],
            'meta.dibuat_nama' => ['nullable', 'string', 'max:150'],
            'meta.dibuat_jabatan' => ['nullable', 'string', 'max:150'],
            'rows' => ['required', 'array'],
            'rows.*.kategori' => ['required', 'string', 'max:100'],
            'rows.*.is_category_header' => ['nullable', 'boolean'],
            'rows.*.no_urut' => ['nullable', 'string', 'max:20'],
            'rows.*.kegiatan' => ['required', 'string', 'max:255'],
            'rows.*.target' => ['required', 'integer', 'min:0', 'max:31'],
            'rows.*.rencana_count' => ['nullable', 'integer', 'min:0', 'max:31'],
            'rows.*.jadwal' => ['nullable', 'array'],
            'rows.*.jadwal.*' => ['integer', 'between:1,31'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            'rows.*.sort_order' => ['nullable', 'integer'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($unit, $month, $year, $validated, $user): void {
            // Save or update meta
            if (! empty($validated['meta'])) {
                PdmJadwalMeta::query()->updateOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'type' => 'harian',
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'doc_number' => $validated['meta']['doc_number'] ?? null,
                        'revision' => $validated['meta']['revision'] ?? '00',
                        'effective_date' => $validated['meta']['effective_date'] ?? null,
                        'page_number' => $validated['meta']['page_number'] ?? '1 dari 1',
                        'disetujui_nama' => $validated['meta']['disetujui_nama'] ?? null,
                        'disetujui_jabatan' => $validated['meta']['disetujui_jabatan'] ?? null,
                        'dibuat_nama' => $validated['meta']['dibuat_nama'] ?? null,
                        'dibuat_jabatan' => $validated['meta']['dibuat_jabatan'] ?? null,
                    ]
                );
            }

            // Sync rows
            PdmJadwalHarian::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach ($validated['rows'] as $index => $row) {
                $days = array_values(array_unique(array_filter(
                    $row['jadwal'] ?? [],
                    fn ($d): bool => is_numeric($d) && (int) $d >= 1 && (int) $d <= 31
                )));
                sort($days);

                PdmJadwalHarian::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'kategori' => $row['kategori'],
                    'is_category_header' => ! empty($row['is_category_header']),
                    'no_urut' => $row['no_urut'] ?? null,
                    'kegiatan' => $row['kegiatan'],
                    'target' => (int) $row['target'],
                    'rencana_count' => isset($row['rencana_count']) ? (int) $row['rencana_count'] : (int) $row['target'],
                    'realisasi_count' => count($days),
                    'jadwal' => $days,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            event: ActivityEvent::Updated,
            description: "Memperbarui jadwal kegiatan harian PdM & Matlev unit {$unit->name} periode {$month}/{$year}",
            subjectType: PdmJadwalHarian::class,
            properties: [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
                'rows_count' => count($validated['rows']),
            ]
        );

        return redirect()
            ->route('pdm.jadwal.harian.index', [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ])
            ->with('success', 'Jadwal kegiatan harian PdM & Matlev berhasil disimpan.');
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::PdmInputView) ||
            $user->hasPermissionTo(PermissionName::PdmLaporanView),
            403
        );

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $monthName = $data['monthName'];
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = "Jadwal-Kegiatan-PdM-Matlev-{$unit->name}-{$monthName}-{$year}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the editable Laporan PdM document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isSunday = $date->isSunday();
            $isSaturday = $date->isSaturday();
            $isHoliday = $holiday !== null;

            return [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_sunday' => $isSunday,
                'is_weekend' => $isSaturday || $isSunday,
                'is_red' => $isSaturday || $isSunday || $isHoliday,
                'holiday' => $holiday?->description,
            ];
        })->all();

        $meta = PdmJadwalMeta::query()
            ->where('unit_id', $unit->id)
            ->where('type', 'harian')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $rows = PdmJadwalHarian::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $monthName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM');

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['pdm.jadwal.harian-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'days' => $days,
            'daysInMonth' => $daysInMonth,
            'rows' => $rows,
            'meta' => $meta,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }
}
