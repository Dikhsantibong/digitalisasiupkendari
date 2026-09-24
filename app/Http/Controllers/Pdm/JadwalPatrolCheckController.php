<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PdmJadwalMeta;
use App\Models\PdmJadwalPatrolCheck;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class JadwalPatrolCheckController extends Controller
{
    private const DEFAULT_STRUCTURE = [
        [
            'kategori' => 'I. HARMES',
            'header' => [
                'no_urut' => 'I',
                'nama' => 'HARMES',
            ],
            'count' => 4,
            'start_num' => 1,
        ],
        [
            'kategori' => 'II. HARLIS',
            'header' => [
                'no_urut' => 'II',
                'nama' => 'HARLIS',
            ],
            'count' => 2,
            'start_num' => 5,
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

        // Fetch employees for this unit (with signature path indicator)
        $employees = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'signature_path']);

        $meta = PdmJadwalMeta::query()->firstOrNew([
            'unit_id' => $unit->id,
            'type' => 'patrol_check',
            'year' => $year,
            'month' => $month,
        ]);

        if (! $meta->exists) {
            // Default signatories according to reference document
            $tlHar = $employees->first(fn ($e): bool => str_contains(strtolower($e->position ?? ''), 'leader pemeliharaan') || str_contains(strtolower($e->position ?? ''), 'tl har'));
            $projectLeader = $employees->first(fn ($e): bool => str_contains(strtolower($e->position ?? ''), 'project leader') || str_contains(strtolower($e->position ?? ''), 'manager') || str_contains(strtolower($e->position ?? ''), 'manajer'));
            $koorHar = $employees->first(fn ($e): bool => str_contains(strtolower($e->position ?? ''), 'koordinator') || str_contains(strtolower($e->position ?? ''), 'supervisor'));

            $meta->doc_number = $meta->doc_number ?: 'PLN-NP-UPKDR/JADWAL-PIKET-PDM-KIT';
            $meta->revision = $meta->revision ?: '00';
            $meta->effective_date = $meta->effective_date ?: Carbon::create($year, $month, 1)->locale('id')->isoFormat('D MMMM Y');
            $meta->page_number = $meta->page_number ?: '1 dari 1';

            $meta->mengetahui_employee_id = $tlHar?->id;
            $meta->mengetahui_nama = $tlHar?->name ?: 'Team Leader Pemeliharaan';
            $meta->mengetahui_jabatan = $tlHar?->position ?: 'Team Leader Pemeliharaan';

            $meta->disetujui_employee_id = $projectLeader?->id;
            $meta->disetujui_nama = $projectLeader?->name ?: 'Project Leader';
            $meta->disetujui_jabatan = $projectLeader?->position ?: 'Project Leader';

            $meta->dibuat_employee_id = $koorHar?->id;
            $meta->dibuat_nama = $koorHar?->name ?: 'Koordinator Pemeliharaan';
            $meta->dibuat_jabatan = $koorHar?->position ?: 'Koordinator Pemeliharaan';
        }

        $savedRecords = PdmJadwalPatrolCheck::query()
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
                // Add category header row (e.g. I. HARMES)
                $rows[] = [
                    'id' => null,
                    'kategori' => $group['kategori'],
                    'is_category_header' => true,
                    'no_urut' => $group['header']['no_urut'],
                    'employee_id' => null,
                    'nama' => $group['header']['nama'],
                    'no_hp' => '',
                    'target' => $targetWorkingDays,
                    'realisasi' => 0,
                    'jadwal' => [],
                    'keterangan' => '',
                    'sort_order' => $sortOrder++,
                ];

                // Add empty rows for personnel under this category
                for ($i = 0; $i < $group['count']; $i++) {
                    $rows[] = [
                        'id' => null,
                        'kategori' => $group['kategori'],
                        'is_category_header' => false,
                        'no_urut' => (string) ($group['start_num'] + $i),
                        'employee_id' => null,
                        'nama' => '',
                        'no_hp' => '',
                        'target' => $targetWorkingDays,
                        'realisasi' => 0,
                        'jadwal' => [],
                        'keterangan' => '',
                        'sort_order' => $sortOrder++,
                    ];
                }
            }
        } else {
            $rows = $savedRecords->map(fn (PdmJadwalPatrolCheck $r, int $idx): array => [
                'id' => $r->id,
                'kategori' => $r->kategori,
                'is_category_header' => (bool) $r->is_category_header,
                'no_urut' => $r->no_urut ?? (string) ($idx + 1),
                'employee_id' => $r->employee_id,
                'nama' => $r->nama,
                'no_hp' => $r->no_hp ?? '',
                'target' => $r->target,
                'realisasi' => count($r->jadwal ?? []),
                'jadwal' => $r->jadwal ?? [],
                'keterangan' => $r->keterangan ?? '',
                'sort_order' => $r->sort_order ?? $idx,
            ])->all();
        }

        // Resolve signature preview URLs for UI
        $signatureMengetahui = $this->resolveSignatureUrl($meta->mengetahui_employee_id);
        $signatureDisetujui = $this->resolveSignatureUrl($meta->disetujui_employee_id);
        $signatureDibuat = $this->resolveSignatureUrl($meta->dibuat_employee_id);

        return Inertia::render('pdm/jadwal/patrol-check/index', [
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
                'employees' => $employees->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'nip' => $e->nip,
                    'position' => $e->position,
                    'has_signature' => ! empty($e->signature_path),
                ])->all(),
            ],
            'days' => $days,
            'target_working_days' => $targetWorkingDays,
            'rows' => $rows,
            'meta' => [
                'id' => $meta->id,
                'doc_number' => $meta->doc_number,
                'revision' => $meta->revision,
                'effective_date' => $meta->effective_date,
                'page_number' => $meta->page_number,
                'mengetahui_employee_id' => $meta->mengetahui_employee_id,
                'mengetahui_nama' => $meta->mengetahui_nama,
                'mengetahui_jabatan' => $meta->mengetahui_jabatan,
                'mengetahui_signature' => $signatureMengetahui,
                'disetujui_employee_id' => $meta->disetujui_employee_id,
                'disetujui_nama' => $meta->disetujui_nama,
                'disetujui_jabatan' => $meta->disetujui_jabatan,
                'disetujui_signature' => $signatureDisetujui,
                'dibuat_employee_id' => $meta->dibuat_employee_id,
                'dibuat_nama' => $meta->dibuat_nama,
                'dibuat_jabatan' => $meta->dibuat_jabatan,
                'dibuat_signature' => $signatureDibuat,
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
            'meta.revision' => ['nullable', 'string', 'max:20'],
            'meta.effective_date' => ['nullable', 'string', 'max:50'],
            'meta.page_number' => ['nullable', 'string', 'max:30'],
            'meta.mengetahui_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'meta.mengetahui_nama' => ['nullable', 'string', 'max:150'],
            'meta.mengetahui_jabatan' => ['nullable', 'string', 'max:150'],
            'meta.disetujui_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'meta.disetujui_nama' => ['nullable', 'string', 'max:150'],
            'meta.disetujui_jabatan' => ['nullable', 'string', 'max:150'],
            'meta.dibuat_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'meta.dibuat_nama' => ['nullable', 'string', 'max:150'],
            'meta.dibuat_jabatan' => ['nullable', 'string', 'max:150'],
            'rows' => ['present', 'array'],
            'rows.*.kategori' => ['required', 'string', 'max:100'],
            'rows.*.is_category_header' => ['boolean'],
            'rows.*.no_urut' => ['nullable', 'string', 'max:20'],
            'rows.*.employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.nama' => ['required', 'string', 'max:255'],
            'rows.*.no_hp' => ['nullable', 'string', 'max:50'],
            'rows.*.target' => ['nullable', 'integer', 'min:0', 'max:31'],
            'rows.*.jadwal' => ['nullable', 'array'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            'rows.*.sort_order' => ['nullable', 'integer'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year): void {
            // Update or create metadata
            if (! empty($validated['meta'])) {
                PdmJadwalMeta::query()->updateOrCreate(
                    [
                        'unit_id' => $unitId,
                        'type' => 'patrol_check',
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'doc_number' => $validated['meta']['doc_number'] ?? null,
                        'revision' => $validated['meta']['revision'] ?? '00',
                        'effective_date' => $validated['meta']['effective_date'] ?? null,
                        'page_number' => $validated['meta']['page_number'] ?? '1 dari 1',
                        'mengetahui_employee_id' => $validated['meta']['mengetahui_employee_id'] ?? null,
                        'mengetahui_nama' => $validated['meta']['mengetahui_nama'] ?? null,
                        'mengetahui_jabatan' => $validated['meta']['mengetahui_jabatan'] ?? null,
                        'disetujui_employee_id' => $validated['meta']['disetujui_employee_id'] ?? null,
                        'disetujui_nama' => $validated['meta']['disetujui_nama'] ?? null,
                        'disetujui_jabatan' => $validated['meta']['disetujui_jabatan'] ?? null,
                        'dibuat_employee_id' => $validated['meta']['dibuat_employee_id'] ?? null,
                        'dibuat_nama' => $validated['meta']['dibuat_nama'] ?? null,
                        'dibuat_jabatan' => $validated['meta']['dibuat_jabatan'] ?? null,
                    ]
                );
            }

            // Sync rows: delete old rows and insert updated rows
            PdmJadwalPatrolCheck::query()
                ->where('unit_id', $unitId)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach ($validated['rows'] as $idx => $r) {
                $jadwal = collect($r['jadwal'] ?? [])
                    ->map(fn ($d): int => (int) $d)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                PdmJadwalPatrolCheck::query()->create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                    'kategori' => $r['kategori'],
                    'is_category_header' => (bool) ($r['is_category_header'] ?? false),
                    'no_urut' => $r['no_urut'] ?? null,
                    'employee_id' => $r['employee_id'] ?? null,
                    'nama' => $r['nama'],
                    'no_hp' => $r['no_hp'] ?? null,
                    'target' => (int) ($r['target'] ?? 23),
                    'jadwal' => $jadwal,
                    'keterangan' => $r['keterangan'] ?? null,
                    'sort_order' => (int) ($r['sort_order'] ?? $idx),
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Piket Patrol Check PdM KIT {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Piket Patrol Check PdM KIT berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::PdmInputView) ||
            $user->hasPermissionTo(PermissionName::PdmLaporanView),
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

        $filename = sprintf(
            'Jadwal_Piket_Patrol_Check_PdM_KIT_%s_%02d_%d.pdf',
            str_replace(' ', '_', $unit->name),
            $month,
            $year
        );

        return $pdf->stream($filename);
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
                'is_holiday' => $isHoliday,
                'is_red' => $isSaturday || $isSunday || $isHoliday,
                'holiday' => $holiday?->description,
            ];
        })->all();

        $meta = PdmJadwalMeta::query()
            ->where('unit_id', $unit->id)
            ->where('type', 'patrol_check')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $rows = PdmJadwalPatrolCheck::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Signatures as Base64 for PDF
        $signatureMengetahui = $this->resolveSignatureBase64($meta?->mengetahui_employee_id);
        $signatureDisetujui = $this->resolveSignatureBase64($meta?->disetujui_employee_id);
        $signatureDibuat = $this->resolveSignatureBase64($meta?->dibuat_employee_id);

        ['logoLeft' => $logoLeft, 'logoRight' => $logoRight] = JadwalPdf::logos();

        $monthName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM');

        return ['pdm.jadwal.patrol-check-pdf', [
            'unit' => $unit,
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'days' => $days,
            'rows' => $rows,
            'meta' => $meta,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
            'signatureMengetahui' => $signatureMengetahui,
            'signatureDisetujui' => $signatureDisetujui,
            'signatureDibuat' => $signatureDibuat,
        ]];
    }

    /**
     * Resolve employee signature as public web URL for frontend display.
     */
    private function resolveSignatureUrl(?int $employeeId): ?string
    {
        if (! $employeeId) {
            return null;
        }

        $employee = Employee::find($employeeId);
        if (! $employee || empty($employee->signature_path)) {
            return null;
        }

        return Storage::disk('public')->url($employee->signature_path);
    }

    /**
     * Resolve employee signature as base64 image data URI for DomPDF rendering.
     */
    private function resolveSignatureBase64(?int $employeeId): ?string
    {
        if (! $employeeId) {
            return null;
        }

        $employee = Employee::find($employeeId);
        if (! $employee || empty($employee->signature_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($employee->signature_path)) {
            return null;
        }

        $path = Storage::disk('public')->path($employee->signature_path);
        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
