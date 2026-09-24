<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarDailyMeeting;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulir Daily Meeting Pemeliharaan (Modul HAR): daftar hadir per meeting
 * (acara, tanggal, waktu, tempat, peserta) dan foto eviden yang dicetak
 * sebagai lembar kedua PDF. Juga masuk Laporan Pemeliharaan lewat pdfView().
 */
class DailyMeetingController extends Controller
{
    /** Baris daftar hadir minimal pada lembar resmi. */
    public const MIN_ROWS = 15;

    public const MAX_EVIDEN = 6;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ReportSignatories $signatories,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($user);
        [$units, $unit, $month, $year] = $this->target($request);

        $meetings = $this->meetings($unit, $month, $year);
        $selected = $meetings->firstWhere('id', $request->integer('meeting_id'));

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
                'peserta' => count($meeting->peserta),
                'eviden' => count($meeting->eviden),
            ])->values()->all(),
            'meeting' => $selected ? $this->present($selected) : null,
            'signers' => $this->signers($unit),
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'id' => ['nullable', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'acara' => ['required', 'string', 'max:255'],
            'waktu' => ['nullable', 'string', 'max:50'],
            'tempat' => ['nullable', 'string', 'max:255'],
            'peserta' => ['present', 'array', 'max:100'],
            'peserta.*.nama' => ['nullable', 'string', 'max:150'],
            'peserta.*.asal' => ['nullable', 'string', 'max:150'],
            'peserta.*.jabatan' => ['nullable', 'string', 'max:150'],
            'keep_eviden' => ['nullable', 'array'],
            'keep_eviden.*' => ['string', 'max:255'],
            'eviden' => ['nullable', 'array'],
            'eviden.*' => ['image', 'max:5120'],
        ]);

        $meeting = isset($validated['id'])
            ? HarDailyMeeting::query()->where('unit_id', $unit->id)->findOrFail($validated['id'])
            : new HarDailyMeeting(['unit_id' => $unit->id, 'eviden' => []]);

        // Only this meeting's own photos can be kept.
        $keep = array_values(array_intersect($meeting->eviden ?? [], (array) ($validated['keep_eviden'] ?? [])));
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

        $tanggal = Carbon::parse($validated['tanggal']);
        $meeting->fill([
            'year' => $tanggal->year,
            'month' => $tanggal->month,
            'tanggal' => $tanggal->format('Y-m-d'),
            'acara' => trim($validated['acara']),
            'waktu' => $this->text($validated['waktu'] ?? null),
            'tempat' => $this->text($validated['tempat'] ?? null),
            'peserta' => collect($validated['peserta'])
                ->map(fn (array $p): array => ['nama' => $this->text($p['nama'] ?? null), 'asal' => $this->text($p['asal'] ?? null), 'jabatan' => $this->text($p['jabatan'] ?? null)])
                ->filter(fn (array $p): bool => $p['nama'] !== null)
                ->values()->all(),
            'eviden' => $keep,
            'input_by' => $user->id,
        ])->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Daily Meeting {$unit->name} {$tanggal->format('d/m/Y')}", $meeting, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daily meeting berhasil disimpan.']);

        return redirect()->route('har.formulir.daily-meeting.index', [
            'unit_id' => $unit->id, 'month' => $tanggal->month, 'year' => $tanggal->year, 'meeting_id' => $meeting->id,
        ]);
    }

    public function destroy(Request $request, HarDailyMeeting $dailyMeeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($dailyMeeting->unit_id), 403);

        foreach ($dailyMeeting->eviden as $path) {
            Storage::disk('public')->delete($path);
        }
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
        [$view, $data] = $this->pdfView($unit, collect([$dailyMeeting]));
        $filename = sprintf('Daily_Meeting_%s_%s.pdf', str_replace(' ', '_', $unit->name), $dailyMeeting->tanggal->format('Ymd'));

        return response(Pdf::loadView($view, $data)->setPaper('a4', 'portrait')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * PDF view & data for the given meetings (each: daftar hadir + lembar eviden) —
     * reused by the Laporan Pemeliharaan for every meeting of the month.
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
     * @return array<string, mixed>
     */
    private function present(HarDailyMeeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'tanggal' => $meeting->tanggal->format('Y-m-d'),
            'acara' => $meeting->acara,
            'waktu' => $meeting->waktu,
            'tempat' => $meeting->tempat,
            'peserta' => $meeting->peserta,
            'eviden' => array_map(fn (string $path): array => ['path' => $path, 'url' => Storage::disk('public')->url($path)], $meeting->eviden),
        ];
    }

    /**
     * Penandatangan lembar: Project Leader (kiri) & Koordinator Pemeliharaan (kanan).
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

    private function authorizeView(User $user): void
    {
        abort_unless($user->hasPermissionTo(PermissionName::HarInputView) || $user->hasPermissionTo(PermissionName::HarLaporanView), 403);
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
