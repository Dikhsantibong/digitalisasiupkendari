<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HarLogbookMutasi;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulir Logbook Mutasi Harian Tim Pemeliharaan (Modul HAR): satu logbook
 * per tanggal — absensi, kesiapan APD, job harian rutin & non rutin, kondisi
 * K3. Masuk Laporan Pemeliharaan lewat pdfView().
 */
class LogbookMutasiController extends Controller
{
    /** @var list<string> */
    public const APD = ['HELM', 'WEARPACK', 'SEPATU SAFETY', 'EAR PLUG', 'SARUNG TANGAN'];

    /** @var list<string> */
    public const RUTIN = ['PATROL CEK'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($user);
        [$units, $unit, $month, $year] = $this->target($request);

        $logbooks = $this->logbooks($unit, $month, $year);
        $tanggal = $request->string('tanggal')->toString();
        $selected = $logbooks->first(fn (HarLogbookMutasi $logbook): bool => $logbook->tanggal->format('Y-m-d') === $tanggal);

        return Inertia::render('har/formulir/logbook-mutasi/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'tanggal' => $selected?->tanggal->format('Y-m-d')],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range((int) now()->year - 3, (int) now()->year + 1),
            ],
            'logbooks' => $logbooks->map(fn (HarLogbookMutasi $logbook): array => [
                'id' => $logbook->id,
                'tanggal' => $logbook->tanggal->format('Y-m-d'),
                'hadir' => count($logbook->absensi),
                'pekerjaan' => count($logbook->rutin) + count($logbook->non_rutin),
            ])->values()->all(),
            'logbook' => $selected ? $this->present($selected) : null,
            'template' => $this->template($unit),
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
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'absensi' => ['present', 'array', 'max:60'],
            'absensi.*.nama' => ['nullable', 'string', 'max:150'],
            'absensi.*.jabatan' => ['nullable', 'string', 'max:150'],
            'absensi.*.keterangan' => ['nullable', 'string', 'max:255'],
            'absensi.*.paraf' => ['nullable', 'string', 'max:50'],
            'apd' => ['present', 'array', 'max:30'],
            'apd.*.item' => ['nullable', 'string', 'max:100'],
            'apd.*.keterangan' => ['nullable', 'string', 'max:255'],
            ...collect(['rutin', 'non_rutin', 'kondisi_k3'])->flatMap(fn (string $list): array => [
                $list => ['present', 'array', 'max:60'],
                "{$list}.*.uraian" => ['nullable', 'string', 'max:1000'],
                "{$list}.*.keterangan" => ['nullable', 'string', 'max:1000'],
            ])->all(),
        ]);

        $tanggal = Carbon::parse($validated['tanggal']);
        $keep = fn (array $rows, string $key, array $fields): array => collect($rows)
            ->map(fn (array $row): array => collect($fields)->mapWithKeys(fn (string $f): array => [$f => $this->text($row[$f] ?? null)])->all())
            ->filter(fn (array $row): bool => $row[$key] !== null)
            ->values()->all();

        $logbook = HarLogbookMutasi::query()->where('unit_id', $unit->id)->whereDate('tanggal', $tanggal->format('Y-m-d'))->first()
            ?? new HarLogbookMutasi(['unit_id' => $unit->id, 'tanggal' => $tanggal->format('Y-m-d')]);
        $logbook->fill([
            'year' => $tanggal->year,
            'month' => $tanggal->month,
            'absensi' => $keep($validated['absensi'], 'nama', ['nama', 'jabatan', 'keterangan', 'paraf']),
            'apd' => $keep($validated['apd'], 'item', ['item', 'keterangan']),
            'rutin' => $keep($validated['rutin'], 'uraian', ['uraian', 'keterangan']),
            'non_rutin' => $keep($validated['non_rutin'], 'uraian', ['uraian', 'keterangan']),
            'kondisi_k3' => $keep($validated['kondisi_k3'], 'uraian', ['uraian', 'keterangan']),
            'input_by' => $user->id,
        ])->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Logbook Mutasi Harian {$unit->name} {$tanggal->format('d/m/Y')}", $logbook, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logbook mutasi harian berhasil disimpan.']);

        return redirect()->route('har.formulir.logbook-mutasi.index', [
            'unit_id' => $unit->id, 'month' => $tanggal->month, 'year' => $tanggal->year, 'tanggal' => $tanggal->format('Y-m-d'),
        ]);
    }

    public function destroy(Request $request, HarLogbookMutasi $logbookMutasi): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($logbookMutasi->unit_id), 403);

        $logbookMutasi->delete();
        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus Logbook Mutasi Harian #{$logbookMutasi->id}", unit: $logbookMutasi->unit_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logbook mutasi harian dihapus.']);

        return redirect()->route('har.formulir.logbook-mutasi.index', ['unit_id' => $logbookMutasi->unit_id, 'month' => $logbookMutasi->month, 'year' => $logbookMutasi->year]);
    }

    public function pdf(Request $request, HarLogbookMutasi $logbookMutasi): HttpResponse
    {
        $user = $request->user();
        $this->authorizeView($user);
        abort_unless($user->canAccessUnit($logbookMutasi->unit_id), 403);

        $unit = Unit::query()->findOrFail($logbookMutasi->unit_id);
        [$view, $data] = $this->pdfView($unit, collect([$logbookMutasi]));
        $filename = sprintf('Logbook_Mutasi_Harian_%s_%s.pdf', str_replace(' ', '_', $unit->name), $logbookMutasi->tanggal->format('Ymd'));

        return response(Pdf::loadView($view, $data)->setPaper('a4', 'portrait')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * PDF view & data for the given logbooks (one page each) — reused by the Laporan Pemeliharaan.
     *
     * @param  Collection<int, HarLogbookMutasi>  $logbooks
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, Collection $logbooks): array
    {
        return ['har.formulir.logbook-mutasi-pdf', [
            'unit' => $unit,
            'logbooks' => $logbooks->map(fn (HarLogbookMutasi $logbook): array => [
                ...$this->present($logbook),
                'hari_tanggal' => Indonesian::dayName(Carbon::parse($logbook->tanggal->format('Y-m-d'))).', '.Indonesian::longDate(Carbon::parse($logbook->tanggal->format('Y-m-d'))),
            ])->values()->all(),
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * @return Collection<int, HarLogbookMutasi>
     */
    public function logbooks(Unit $unit, int $month, int $year): Collection
    {
        return HarLogbookMutasi::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->orderBy('tanggal')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(HarLogbookMutasi $logbook): array
    {
        return [
            'id' => $logbook->id,
            'tanggal' => $logbook->tanggal->format('Y-m-d'),
            ...$logbook->only(['absensi', 'apd', 'rutin', 'non_rutin', 'kondisi_k3']),
        ];
    }

    /**
     * Isian bawaan logbook baru: pegawai aktif bagian pemeliharaan unit ini,
     * daftar APD dan job rutin dari lembar resmi (keterangan kosong).
     *
     * @return array<string, list<array<string, string|null>>>
     */
    private function template(Unit $unit): array
    {
        $staff = Employee::query()->where('unit_id', $unit->id)->where('is_active', true)->where('division', 'pemeliharaan')
            ->orderBy('name')->get(['name', 'position']);

        return [
            'absensi' => $staff->map(fn (Employee $e): array => ['nama' => $e->name, 'jabatan' => $e->position, 'keterangan' => null, 'paraf' => null])->values()->all(),
            'apd' => array_map(fn (string $item): array => ['item' => $item, 'keterangan' => null], self::APD),
            'rutin' => array_map(fn (string $uraian): array => ['uraian' => $uraian, 'keterangan' => null], self::RUTIN),
            'non_rutin' => [],
            'kondisi_k3' => [],
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
}
