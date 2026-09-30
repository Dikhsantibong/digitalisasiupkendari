<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\HarDailyMeeting;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Har\DailyMeetingAttendance;
use App\Services\Reports\ReportSignatories;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily Meeting Pemeliharaan (Modul HAR, Akses 1): the Koordinator
 * Pemeliharaan / Project Leader creates a meeting (acara, hari/tanggal, waktu,
 * tempat), shows its QR code, and the attendees scan it to fill the attendance
 * form (nama, asal perusahaan, jabatan, canvas signature) on their own phone
 * (Har\MeetingAttendanceController). Eviden photos print as the second sheet.
 * The PDF also goes into the Laporan Pemeliharaan through pdfView().
 */
class DailyMeetingController extends Controller
{
    use AuthorizesFieldInput;

    /** Minimum attendance rows on the printed sheet. */
    public const MIN_ROWS = 15;

    public const MAX_EVIDEN = 6;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ReportSignatories $signatories,
        private readonly DailyMeetingAttendance $attendance,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($user);
        [$units, $unit, $month, $year] = $this->target($request);

        $meetings = $this->meetings($unit, $month, $year);
        $selected = $meetings->firstWhere('id', $request->integer('meeting_id'));
        if ($selected !== null) {
            $this->assignUids($selected);
        }

        return Inertia::render('har/formulir/daily-meeting/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'meeting_id' => $selected?->id],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range((int) now()->year - 3, (int) now()->year + 1),
                'min_rows' => self::MIN_ROWS,
                'max_eviden' => self::MAX_EVIDEN,
            ],
            'meetings' => $meetings->map(fn (HarDailyMeeting $meeting): array => [
                'id' => $meeting->id,
                'tanggal' => $meeting->tanggal->format('Y-m-d'),
                'acara' => $meeting->acara,
                'waktu' => $meeting->waktu,
                'tempat' => $meeting->tempat,
                'peserta' => count($meeting->peserta),
                'eviden' => count($meeting->eviden),
                'absensi_dibuka' => $meeting->absensi_dibuka,
            ])->values()->all(),
            'meeting' => $selected ? [
                ...$this->present($selected),
                'absensi_url' => $this->attendance->url($selected),
                'qr_svg' => $this->attendance->qrSvg($selected),
            ] : null,
            'signers' => $this->signers($unit),
            'today' => now()->format('Y-m-d'),
            'can_write' => $this->canWrite($user),
        ]);
    }

    /**
     * Create or edit a meeting. `peserta` (full list) is optional: the attendance
     * normally comes in through the QR form; eviden photos only change when
     * `update_eviden` is sent.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'id' => ['nullable', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'acara' => ['required', 'string', 'max:255'],
            'waktu' => ['nullable', 'string', 'max:50'],
            'tempat' => ['nullable', 'string', 'max:255'],
            'peserta' => ['sometimes', 'array', 'max:150'],
            'peserta.*.uid' => ['nullable', 'string', 'max:40'],
            'peserta.*.nama' => ['nullable', 'string', 'max:150'],
            'peserta.*.asal' => ['nullable', 'string', 'max:150'],
            'peserta.*.jabatan' => ['nullable', 'string', 'max:150'],
            'update_eviden' => ['nullable', 'boolean'],
            'keep_eviden' => ['nullable', 'array'],
            'keep_eviden.*' => ['string', 'max:255'],
            'eviden' => ['nullable', 'array'],
            'eviden.*' => ['image', 'max:5120'],
        ], [
            'acara.required' => 'Acara meeting wajib diisi.',
            'tanggal.required' => 'Hari / tanggal meeting wajib diisi.',
        ]);

        $meeting = isset($validated['id'])
            ? HarDailyMeeting::query()->where('unit_id', $unit->id)->findOrFail($validated['id'])
            : new HarDailyMeeting(['unit_id' => $unit->id, 'eviden' => [], 'peserta' => [], 'absensi_dibuka' => true]);
        $isNew = ! $meeting->exists;

        $eviden = $meeting->eviden ?? [];
        if ($request->boolean('update_eviden') || $request->has('keep_eviden') || $request->hasFile('eviden')) {
            $eviden = $this->syncEviden($request, $meeting, $unit, (array) ($validated['keep_eviden'] ?? []));
        }

        $tanggal = Carbon::parse($validated['tanggal']);
        $meeting->fill([
            'year' => $tanggal->year,
            'month' => $tanggal->month,
            'tanggal' => $tanggal->format('Y-m-d'),
            'acara' => trim($validated['acara']),
            'waktu' => $this->text($validated['waktu'] ?? null),
            'tempat' => $this->text($validated['tempat'] ?? null),
            'peserta' => array_key_exists('peserta', $validated) ? $this->mergePeserta($meeting, $validated['peserta']) : ($meeting->peserta ?? []),
            'eviden' => $eviden,
            'input_by' => $user->id,
        ])->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Daily Meeting {$unit->name} {$tanggal->format('d/m/Y')}", $meeting, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => $isNew ? 'Meeting dibuat. Tampilkan QR code agar peserta bisa absen.' : 'Daily meeting disimpan.']);

        return redirect()->route('har.formulir.daily-meeting.index', [
            'unit_id' => $unit->id, 'month' => $tanggal->month, 'year' => $tanggal->year, 'meeting_id' => $meeting->id,
        ]);
    }

    /**
     * Open or close the QR attendance of a meeting.
     */
    public function toggleAbsensi(Request $request, HarDailyMeeting $dailyMeeting): RedirectResponse
    {
        $this->authorizeWrite($request->user(), $dailyMeeting);
        $validated = $request->validate(['dibuka' => ['required', 'boolean']]);

        $dailyMeeting->forceFill(['absensi_dibuka' => (bool) $validated['dibuka']])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => $dailyMeeting->absensi_dibuka ? 'Absensi dibuka kembali.' : 'Absensi ditutup. QR code tidak lagi menerima peserta.']);

        return back();
    }

    /**
     * Attendance filled on the organiser's device (attendee without a phone).
     */
    public function storePeserta(Request $request, HarDailyMeeting $dailyMeeting): RedirectResponse
    {
        $this->authorizeWrite($request->user(), $dailyMeeting);
        $validated = $request->validate(DailyMeetingAttendance::rules(), DailyMeetingAttendance::messages());

        $attendee = $this->attendance->checkIn($dailyMeeting, $validated, 'manual');
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$attendee['nama']} tercatat hadir."]);

        return back();
    }

    public function destroyPeserta(Request $request, HarDailyMeeting $dailyMeeting, string $uid): RedirectResponse
    {
        $this->authorizeWrite($request->user(), $dailyMeeting);

        $this->attendance->remove($dailyMeeting, $uid);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peserta dihapus dari daftar hadir.']);

        return back();
    }

    public function destroy(Request $request, HarDailyMeeting $dailyMeeting): RedirectResponse
    {
        $this->authorizeWrite($request->user(), $dailyMeeting);

        foreach ($dailyMeeting->eviden as $path) {
            Storage::disk('public')->delete($path);
        }
        $this->attendance->forget($dailyMeeting);
        $dailyMeeting->delete();

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus Daily Meeting #{$dailyMeeting->id}", unit: $dailyMeeting->unit_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily meeting dihapus.']);

        return redirect()->route('har.formulir.daily-meeting.index', ['unit_id' => $dailyMeeting->unit_id, 'month' => $dailyMeeting->month, 'year' => $dailyMeeting->year]);
    }

    public function pdf(Request $request, HarDailyMeeting $dailyMeeting): HttpResponse
    {
        $user = $request->user();
        $this->authorizeView($user);
        abort_unless($user->canAccessUnit($dailyMeeting->unit_id), 403);

        $unit = Unit::query()->findOrFail($dailyMeeting->unit_id);
        $filename = sprintf('Daily_Meeting_%s_%s.pdf', str_replace(' ', '_', $unit->name), $dailyMeeting->tanggal->format('Ymd'));

        return $this->pdfResponse($request, $unit, collect([$dailyMeeting]), $filename);
    }

    /**
     * Every meeting of the month in one PDF.
     */
    public function pdfMonth(Request $request): HttpResponse
    {
        $user = $request->user();
        $this->authorizeView($user);
        [, $unit, $month, $year] = $this->target($request);

        $meetings = $this->meetings($unit, $month, $year);
        abort_if($meetings->isEmpty(), 404, 'Belum ada meeting pada periode ini.');

        $filename = sprintf('Daily_Meeting_%s_%04d_%02d.pdf', str_replace(' ', '_', $unit->name), $year, $month);

        return $this->pdfResponse($request, $unit, $meetings, $filename);
    }

    /**
     * PDF view & data for the given meetings (each: daftar hadir with the
     * signatures + lembar eviden) — reused by the Laporan Pemeliharaan for every
     * meeting of the month.
     *
     * @param  Collection<int, HarDailyMeeting>  $meetings
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, Collection $meetings): array
    {
        return ['har.formulir.daily-meeting-pdf', [
            'unit' => $unit,
            'meetings' => $meetings->map(fn (HarDailyMeeting $meeting): array => [
                ...$this->present($meeting),
                'hari_tanggal' => Indonesian::dayName(Carbon::parse($meeting->tanggal->format('Y-m-d'))).' / '.Indonesian::longDate(Carbon::parse($meeting->tanggal->format('Y-m-d'))),
                'ttd_images' => array_map(fn (array $p): ?string => empty($p['ttd']) ? null : $this->embed($p['ttd']), $meeting->peserta),
                'eviden_images' => array_values(array_filter(array_map(fn (string $path): ?string => $this->embed($path), $meeting->eviden))),
            ])->values()->all(),
            'minRows' => self::MIN_ROWS,
            'signers' => $this->signers($unit),
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * @return Collection<int, HarDailyMeeting>
     */
    public function meetings(Unit $unit, int $month, int $year): Collection
    {
        return HarDailyMeeting::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('tanggal')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, HarDailyMeeting>  $meetings
     */
    private function pdfResponse(Request $request, Unit $unit, Collection $meetings, string $filename): HttpResponse
    {
        [$view, $data] = $this->pdfView($unit, $meetings);

        return response(Pdf::loadView($view, $data)->setPaper('a4', 'portrait')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  list<string>  $keepRequested
     * @return list<string>
     */
    private function syncEviden(Request $request, HarDailyMeeting $meeting, Unit $unit, array $keepRequested): array
    {
        // Only this meeting's own photos can be kept.
        $keep = array_values(array_intersect($meeting->eviden ?? [], $keepRequested));
        $uploads = array_values(array_filter((array) $request->file('eviden', []), fn ($file): bool => $file instanceof UploadedFile));
        if (count($keep) + count($uploads) > self::MAX_EVIDEN) {
            throw ValidationException::withMessages(['eviden' => 'Foto eviden maksimal '.self::MAX_EVIDEN.' foto per meeting.']);
        }

        foreach (array_diff($meeting->eviden ?? [], $keep) as $removed) {
            Storage::disk('public')->delete($removed);
        }
        foreach ($uploads as $file) {
            $keep[] = $file->store("har-daily-meeting/{$unit->id}", 'public');
        }

        return $keep;
    }

    /**
     * The attendance list typed by the organiser: rows keep their signature by uid;
     * dropped rows lose their signature file.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mergePeserta(HarDailyMeeting $meeting, array $rows): array
    {
        $existing = collect($meeting->peserta ?? [])->keyBy(fn (array $p, int $i): string => $p['uid'] ?? "idx-{$i}");

        $merged = collect($rows)
            ->map(function (array $row) use ($existing): array {
                $old = isset($row['uid']) ? $existing->get($row['uid']) : null;

                return [
                    'uid' => $old['uid'] ?? (string) Str::ulid(),
                    'nama' => $this->text($row['nama'] ?? null),
                    'asal' => $this->text($row['asal'] ?? null),
                    'jabatan' => $this->text($row['jabatan'] ?? null),
                    'ttd' => $old['ttd'] ?? null,
                    'hadir_pada' => $old['hadir_pada'] ?? null,
                    'via' => $old['via'] ?? 'manual',
                ];
            })
            ->filter(fn (array $p): bool => $p['nama'] !== null)
            ->values();

        $keptSignatures = $merged->pluck('ttd')->filter()->all();
        foreach ($existing as $old) {
            if (! empty($old['ttd']) && ! in_array($old['ttd'], $keptSignatures, true)) {
                Storage::disk('public')->delete($old['ttd']);
            }
        }

        return $merged->all();
    }

    /**
     * Older rows (before the QR attendance) have no uid yet.
     */
    private function assignUids(HarDailyMeeting $meeting): void
    {
        if (collect($meeting->peserta)->every(fn (array $p): bool => isset($p['uid']))) {
            return;
        }

        $meeting->forceFill(['peserta' => collect($meeting->peserta)->map(fn (array $p): array => [
            'uid' => (string) Str::ulid(), 'ttd' => null, 'hadir_pada' => null, 'via' => 'manual', ...$p,
        ])->all()])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(HarDailyMeeting $meeting): array
    {
        $disk = Storage::disk('public');

        return [
            'id' => $meeting->id,
            'tanggal' => $meeting->tanggal->format('Y-m-d'),
            'hari' => Indonesian::dayName(Carbon::parse($meeting->tanggal->format('Y-m-d'))),
            'acara' => $meeting->acara,
            'waktu' => $meeting->waktu,
            'tempat' => $meeting->tempat,
            'absensi_dibuka' => $meeting->absensi_dibuka,
            'peserta' => array_map(fn (array $p): array => [
                'uid' => $p['uid'] ?? null,
                'nama' => $p['nama'] ?? '',
                'asal' => $p['asal'] ?? null,
                'jabatan' => $p['jabatan'] ?? null,
                'ttd_url' => empty($p['ttd']) ? null : $disk->url($p['ttd']),
                'hadir_pada' => $p['hadir_pada'] ?? null,
                'via' => $p['via'] ?? 'manual',
            ], $meeting->peserta),
            'eviden' => array_map(fn (string $path): array => ['path' => $path, 'url' => $disk->url($path)], $meeting->eviden),
        ];
    }

    /**
     * Sheet signers: Project Leader (left) & Koordinator Pemeliharaan (right).
     *
     * @return array{kiri: array{jabatan: string, nama: string}, kanan: array{jabatan: string, nama: string}}
     */
    private function signers(Unit $unit): array
    {
        return [
            'kiri' => ['jabatan' => 'Project Leader', 'nama' => (string) $this->signatories->holder($unit, EmployeePosition::ProjectLeader)?->name],
            'kanan' => ['jabatan' => 'Koordinator HAR', 'nama' => (string) $this->signatories->holder($unit, EmployeePosition::KoordinatorPemeliharaan)?->name],
        ];
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganDailyMeeting);
    }

    private function authorizeWrite(User $user, HarDailyMeeting $meeting): void
    {
        abort_unless($this->canWrite($user), 403);
        abort_unless($user->canAccessUnit($meeting->unit_id), 403);
    }

    private function authorizeView(User $user): void
    {
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganDailyMeeting) || $user->hasPermissionTo(PermissionName::HarLaporanView), 403);
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);
        $now = Carbon::now();

        return [
            $units,
            $unit,
            max(1, min(12, $request->integer('month') ?: (int) $now->month)),
            max(2000, min(2100, $request->integer('year') ?: (int) $now->year)),
        ];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function embed(string $path): ?string
    {
        $disk = Storage::disk('public');

        return $disk->exists($path)
            ? 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path))
            : null;
    }
}
