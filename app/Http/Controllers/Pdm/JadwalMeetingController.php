<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PdmJadwalMeeting;
use App\Models\PdmJadwalMeta;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class JadwalMeetingController extends Controller
{
    private const DEFAULT_URAIAN = [
        'OFFICER PdM & Matlev',
    ];

    private const DEFAULT_CATATAN = "Ditambahkan eviden dan daftar hadir\nMinimal pelaksanaan meeting evaluasi berkala\nDapat menggunakan eviden yang sama dengan serah terima tugas, aplikasi tersedia\nAngka realisasi dan kinerja dijadikan rekomendasi laporan operasi dan project";

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

            $dow = match ($date->dayOfWeek) {
                Carbon::MONDAY => 'SN',
                Carbon::TUESDAY => 'SL',
                Carbon::WEDNESDAY => 'RB',
                Carbon::THURSDAY => 'KM',
                Carbon::FRIDAY => 'JM',
                Carbon::SATURDAY => 'SB',
                Carbon::SUNDAY => 'MG',
            };

            return [
                'day' => $day,
                'dow' => $dow,
                'is_sunday' => $isSunday,
                'is_weekend' => $isSaturday || $isSunday,
                'is_holiday' => $isHoliday,
                'is_red' => $isSaturday || $isSunday || $isHoliday,
                'holiday' => $holiday?->description,
            ];
        })->all();

        // Fetch employees for this unit (with signature path indicator)
        $employees = Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'signature_path']);

        $meta = PdmJadwalMeta::query()->firstOrNew([
            'unit_id' => $unit->id,
            'type' => 'meeting',
            'year' => $year,
            'month' => $month,
        ]);

        if (! $meta->exists) {
            $tlHar = $employees->first(fn ($e): bool => str_contains(strtolower($e->position ?? ''), 'leader pemeliharaan') || str_contains(strtolower($e->position ?? ''), 'tl har'));
            $projectLeader = $employees->first(fn ($e): bool => str_contains(strtolower($e->position ?? ''), 'project leader') || str_contains(strtolower($e->position ?? ''), 'manager') || str_contains(strtolower($e->position ?? ''), 'manajer'));
            $koorHar = $employees->first(fn ($e): bool => str_contains(strtolower($e->position ?? ''), 'koordinator') || str_contains(strtolower($e->position ?? ''), 'supervisor'));

            $meta->doc_number = $meta->doc_number ?: 'PLN-NP-UPKDR/JADWAL-MEETING-PDM';
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

        $savedRecords = PdmJadwalMeeting::query()
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
                'target' => 4,
                'catatan' => self::DEFAULT_CATATAN,
                'keterangan' => '',
                'sort_order' => $idx,
            ])->all();
        } else {
            $rows = $savedRecords->map(fn (PdmJadwalMeeting $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?? ($idx + 1),
                'uraian' => $r->uraian,
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'target' => $r->target,
                'catatan' => $r->catatan ?? self::DEFAULT_CATATAN,
                'keterangan' => $r->keterangan ?? '',
                'sort_order' => $r->sort_order ?? $idx,
            ])->all();
        }

        $catatan = $savedRecords->first()?->catatan ?: self::DEFAULT_CATATAN;

        // Resolve signature preview URLs for UI
        $signatureMengetahui = $this->resolveSignatureUrl($meta->mengetahui_employee_id);
        $signatureDisetujui = $this->resolveSignatureUrl($meta->disetujui_employee_id);
        $signatureDibuat = $this->resolveSignatureUrl($meta->dibuat_employee_id);

        return Inertia::render('pdm/jadwal/meeting/index', [
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
            'rows' => $rows,
            'catatan' => $catatan,
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
            'catatan' => ['nullable', 'string'],
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
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.uraian' => ['required', 'string', 'max:255'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.target' => ['nullable', 'integer', 'min:0'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            'rows.*.sort_order' => ['nullable', 'integer'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $catatan = $validated['catatan'] ?? self::DEFAULT_CATATAN;

        DB::transaction(function () use ($validated, $user, $unitId, $month, $year, $catatan): void {
            if (! empty($validated['meta'])) {
                PdmJadwalMeta::query()->updateOrCreate(
                    [
                        'unit_id' => $unitId,
                        'type' => 'meeting',
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

            PdmJadwalMeeting::query()
                ->where('unit_id', $unitId)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            $cleanDays = fn ($arr) => collect($arr ?? [])
                ->map(fn ($d): int => (int) $d)
                ->filter(fn ($d): bool => $d >= 1 && $d <= 31)
                ->unique()
                ->sort()
                ->values()
                ->all();

            foreach ($validated['rows'] as $idx => $r) {
                PdmJadwalMeeting::query()->create([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $r['no_urut'] ?? ($idx + 1),
                    'uraian' => $r['uraian'],
                    'rencana' => $cleanDays($r['rencana'] ?? []),
                    'realisasi' => $cleanDays($r['realisasi'] ?? []),
                    'target' => (int) ($r['target'] ?? 4),
                    'catatan' => $catatan,
                    'keterangan' => $r['keterangan'] ?? null,
                    'sort_order' => (int) ($r['sort_order'] ?? $idx),
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Jadwal Meeting PdM {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadwal Meeting PdM Pembangkit berhasil disimpan.',
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
            'Jadwal_Meeting_PdM_Pembangkit_%s_%02d_%d.pdf',
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

            $dow = match ($date->dayOfWeek) {
                Carbon::MONDAY => 'SN',
                Carbon::TUESDAY => 'SL',
                Carbon::WEDNESDAY => 'RB',
                Carbon::THURSDAY => 'KM',
                Carbon::FRIDAY => 'JM',
                Carbon::SATURDAY => 'SB',
                Carbon::SUNDAY => 'MG',
            };

            return [
                'day' => $day,
                'dow' => $dow,
                'is_sunday' => $isSunday,
                'is_weekend' => $isSaturday || $isSunday,
                'is_holiday' => $isHoliday,
                'is_red' => $isSaturday || $isSunday || $isHoliday,
                'holiday' => $holiday?->description,
            ];
        })->all();

        $meta = PdmJadwalMeta::query()
            ->where('unit_id', $unit->id)
            ->where('type', 'meeting')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $rows = PdmJadwalMeeting::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $catatan = $rows->first()?->catatan ?: self::DEFAULT_CATATAN;

        $signatureMengetahui = $this->resolveSignatureBase64($meta?->mengetahui_employee_id);
        $signatureDisetujui = $this->resolveSignatureBase64($meta?->disetujui_employee_id);
        $signatureDibuat = $this->resolveSignatureBase64($meta?->dibuat_employee_id);

        $logoPlnPath = public_path('images/logos/pln-logo.png');
        $logoMkpPath = public_path('images/logos/mkp-logo.png');

        $logoLeft = is_file($logoPlnPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPlnPath))
            : null;
        $logoRight = is_file($logoMkpPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoMkpPath))
            : null;

        $monthName = Carbon::create($year, $month, 1)->locale('id')->isoFormat('MMMM');

        return ['pdm.jadwal.meeting-pdf', [
            'unit' => $unit,
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'days' => $days,
            'rows' => $rows,
            'catatan' => $catatan,
            'meta' => $meta,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
            'signatureMengetahui' => $signatureMengetahui,
            'signatureDisetujui' => $signatureDisetujui,
            'signatureDibuat' => $signatureDibuat,
        ]];
    }

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
