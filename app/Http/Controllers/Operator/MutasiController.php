<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\OperatorMutasi;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operator\AttendanceRoster;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lembar Mutasi Operator (logbook mutasi harian): the shift handover sheet of a
 * unit, one per date + shift. The handing-over regu records fuel & lube levels
 * per machine, the monthly tank level, equipment, feeder / start-stop events
 * and machine faults, then signs ("Serahkan"); the receiving regu signs
 * ("Terima"), which locks the sheet. New sheets start from the unit's active
 * machines and the previous sheet (tank, equipment and machine status).
 */
class MutasiController extends Controller
{
    private const TIMEZONE = 'Asia/Makassar';

    /** Largest accepted paraf PNG (decoded bytes). */
    private const MAX_PARAF_BYTES = 1_500_000;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorMutasiView), 403);

        [$units, $unit] = $this->resolveUnit($request);
        [$tanggal, $shift] = $this->resolveSlot($request);

        $mutasi = OperatorMutasi::query()->where('unit_id', $unit->id)->whereDate('tanggal', $tanggal)->where('shift', $shift)->first();
        $employee = $this->employeeOf($user, $unit);
        $canWrite = $user->hasPermissionTo(PermissionName::OperatorMutasiWrite);

        return Inertia::render('operator/mutasi/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'tanggal' => $tanggal, 'shift' => $shift],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all(),
                'shifts' => collect(OperatorMutasi::SHIFTS)->map(fn (string $jam, string $key): array => ['value' => $key, 'label' => ucfirst($key), 'jam' => $jam])->values()->all(),
                'regu' => AttendanceRoster::REGU,
                'pelumas' => OperatorMutasi::PELUMAS,
                'status_mesin' => OperatorMutasi::STATUS_MESIN,
            ],
            'mutasi' => $mutasi ? $this->present($mutasi) : $this->draft($unit, $tanggal, $shift),
            'riwayat' => OperatorMutasi::query()->where('unit_id', $unit->id)
                ->orderByDesc('tanggal')->orderByDesc('id')->limit(12)->get()
                ->map(fn (OperatorMutasi $m): array => [
                    'id' => $m->id,
                    'tanggal' => $m->tanggal->toDateString(),
                    'shift' => $m->shift,
                    'regu_penyerah' => $m->regu_penyerah,
                    'regu_penerima' => $m->regu_penerima,
                    'status' => $this->status($m),
                ])->values()->all(),
            'me' => ['nama' => $employee?->name ?? $user->name, 'regu' => $employee?->regu],
            'can_write' => $canWrite && ! $mutasi?->isReceived(),
            'can_receive' => $canWrite && $mutasi !== null && $mutasi->diserahkan_at !== null && ! $mutasi->isReceived(),
        ]);
    }

    /**
     * Save the sheet; with `serahkan` + `paraf` the handing-over regu also signs it.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorMutasiWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'shift' => ['required', Rule::in(array_keys(OperatorMutasi::SHIFTS))],
            'mesin' => ['present', 'array', 'max:40'],
            'mesin.*.machine_id' => ['nullable', 'integer'],
            'mesin.*.nama' => ['required', 'string', 'max:100'],
            'mesin.*.level_bbm' => ['nullable', 'string', 'max:30'],
            'mesin.*.tambah_bbm' => ['nullable', 'string', 'max:30'],
            'mesin.*.pelumas' => ['nullable', Rule::in(OperatorMutasi::PELUMAS)],
            'mesin.*.status' => ['nullable', Rule::in(OperatorMutasi::STATUS_MESIN)],
            'tangki' => ['present', 'array', 'max:10'],
            'tangki.*.nama' => ['nullable', 'string', 'max:50'],
            'tangki.*.level_cm' => ['nullable', 'string', 'max:20'],
            'peralatan' => ['present', 'array', 'max:20'],
            'peralatan.*.nama' => ['nullable', 'string', 'max:100'],
            'peralatan.*.ada' => ['boolean'],
            'peralatan.*.jumlah' => ['nullable', 'integer', 'min:0', 'max:999'],
            'kejadian' => ['present', 'array', 'max:60'],
            'kejadian.*.jam' => ['nullable', 'date_format:H:i'],
            'kejadian.*.uraian' => ['nullable', 'string', 'max:1000'],
            'gangguan_mesin' => ['nullable', 'string', 'max:3000'],
            'catatan' => ['nullable', 'string', 'max:3000'],
            'regu_penyerah' => ['nullable', Rule::in(AttendanceRoster::REGU)],
            'penyerah_nama' => ['nullable', 'string', 'max:150'],
            'serahkan' => ['nullable', 'boolean'],
            'paraf' => ['nullable', 'string', 'starts_with:data:image/png;base64,', 'max:2100000'],
        ], [
            'mesin.*.nama.required' => 'Nama mesin wajib diisi.',
            'kejadian.*.jam.date_format' => 'Format jam kejadian harus JJ:MM.',
        ]);

        $mutasi = OperatorMutasi::query()->where('unit_id', $unit->id)->whereDate('tanggal', $validated['tanggal'])->where('shift', $validated['shift'])->first()
            ?? new OperatorMutasi(['unit_id' => $unit->id, 'tanggal' => $validated['tanggal'], 'shift' => $validated['shift']]);

        if ($mutasi->isReceived()) {
            throw ValidationException::withMessages(['shift' => 'Lembar mutasi ini sudah diterima regu berikutnya dan terkunci.']);
        }

        $serahkan = $request->boolean('serahkan');
        if ($serahkan) {
            if (empty($validated['regu_penyerah'])) {
                throw ValidationException::withMessages(['regu_penyerah' => 'Pilih regu penyerah.']);
            }
            if (empty($validated['paraf']) && $mutasi->paraf_penyerah === null) {
                throw ValidationException::withMessages(['paraf' => 'Paraf regu penyerah wajib dibuat.']);
            }
        }

        $mutasi->fill([
            'mesin' => collect($validated['mesin'])->map(fn (array $m): array => [
                'machine_id' => isset($m['machine_id']) ? (int) $m['machine_id'] : null,
                'nama' => trim($m['nama']),
                'level_bbm' => $this->text($m['level_bbm'] ?? null),
                'tambah_bbm' => $this->text($m['tambah_bbm'] ?? null),
                'pelumas' => $m['pelumas'] ?? null,
                'status' => $m['status'] ?? null,
            ])->values()->all(),
            'tangki' => collect($validated['tangki'])
                ->map(fn (array $t): array => ['nama' => $this->text($t['nama'] ?? null), 'level_cm' => $this->text($t['level_cm'] ?? null)])
                ->filter(fn (array $t): bool => $t['nama'] !== null || $t['level_cm'] !== null)->values()->all(),
            'peralatan' => collect($validated['peralatan'])
                ->map(fn (array $p): array => ['nama' => $this->text($p['nama'] ?? null), 'ada' => (bool) ($p['ada'] ?? false), 'jumlah' => isset($p['jumlah']) ? (int) $p['jumlah'] : null])
                ->filter(fn (array $p): bool => $p['nama'] !== null)->values()->all(),
            'kejadian' => collect($validated['kejadian'])
                ->map(fn (array $k): array => ['jam' => $k['jam'] ?? null, 'uraian' => trim((string) ($k['uraian'] ?? ''))])
                ->filter(fn (array $k): bool => $k['uraian'] !== '')->values()->all(),
            'gangguan_mesin' => $this->text($validated['gangguan_mesin'] ?? null),
            'catatan' => $this->text($validated['catatan'] ?? null),
            'regu_penyerah' => $validated['regu_penyerah'] ?? null,
            'penyerah_nama' => $this->text($validated['penyerah_nama'] ?? null),
            'input_by' => $user->id,
        ]);

        if ($serahkan) {
            if (! empty($validated['paraf'])) {
                $this->replaceParaf($mutasi, 'paraf_penyerah', $validated['paraf']);
            }
            $mutasi->diserahkan_at = now();
        }

        $mutasi->save();

        $label = $this->label($mutasi);
        $this->activityLogger->log(ActivityEvent::Updated, ($serahkan ? 'Menyerahkan' : 'Menyimpan')." Lembar Mutasi Operator {$unit->name} {$label}", $mutasi, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => $serahkan ? 'Lembar mutasi diserahkan. Menunggu regu penerima.' : 'Lembar mutasi disimpan.']);

        return $this->backTo($mutasi);
    }

    /**
     * The receiving regu signs; the sheet is then locked.
     */
    public function terima(Request $request, OperatorMutasi $mutasi): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorMutasiWrite), 403);
        abort_unless($user->canAccessUnit($mutasi->unit_id), 403);

        $validated = $request->validate([
            'regu_penerima' => ['required', Rule::in(AttendanceRoster::REGU)],
            'penerima_nama' => ['nullable', 'string', 'max:150'],
            'paraf' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:2100000'],
        ], [
            'regu_penerima.required' => 'Pilih regu penerima.',
            'paraf.required' => 'Paraf regu penerima wajib dibuat.',
        ]);

        if ($mutasi->diserahkan_at === null) {
            throw ValidationException::withMessages(['regu_penerima' => 'Lembar mutasi belum diserahkan oleh regu penyerah.']);
        }
        if ($mutasi->isReceived()) {
            throw ValidationException::withMessages(['regu_penerima' => 'Lembar mutasi ini sudah diterima.']);
        }
        if ($validated['regu_penerima'] === $mutasi->regu_penyerah) {
            throw ValidationException::withMessages(['regu_penerima' => 'Regu penerima harus berbeda dengan regu penyerah.']);
        }

        $this->replaceParaf($mutasi, 'paraf_penerima', $validated['paraf']);
        $mutasi->fill([
            'regu_penerima' => $validated['regu_penerima'],
            'penerima_nama' => $this->text($validated['penerima_nama'] ?? null),
            'diterima_at' => now(),
        ])->save();

        $this->activityLogger->log(ActivityEvent::Updated, 'Menerima Lembar Mutasi Operator '.$this->label($mutasi), $mutasi, unit: $mutasi->unit_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas diterima. Lembar mutasi terkunci.']);

        return $this->backTo($mutasi);
    }

    public function pdf(Request $request, OperatorMutasi $mutasi): HttpResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorMutasiView), 403);
        abort_unless($user->canAccessUnit($mutasi->unit_id), 403);

        $unit = Unit::query()->findOrFail($mutasi->unit_id);
        $tanggal = Carbon::parse($mutasi->tanggal->toDateString());

        $pdf = Pdf::loadView('operator.mutasi-pdf', [
            'unit' => $unit,
            'mutasi' => $mutasi,
            'shifts' => OperatorMutasi::SHIFTS,
            'hariTanggal' => Indonesian::dayName($tanggal).', '.Indonesian::longDate($tanggal),
            'parafPenyerah' => $this->embed($mutasi->paraf_penyerah),
            'parafPenerima' => $this->embed($mutasi->paraf_penerima),
            ...JadwalPdf::logos(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('Mutasi_Operator_%s_%s_%s.pdf', str_replace(' ', '_', $unit->name), $tanggal->format('Ymd'), $mutasi->shift);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * A new sheet: the unit's active machines, with tank, equipment and machine
     * status carried over from the previous sheet.
     *
     * @return array<string, mixed>
     */
    private function draft(Unit $unit, string $tanggal, string $shift): array
    {
        $previous = OperatorMutasi::query()->where('unit_id', $unit->id)
            ->where(fn ($q) => $q->whereDate('tanggal', '<', $tanggal)->orWhere(fn ($q) => $q->whereDate('tanggal', $tanggal)->where('shift', '!=', $shift)))
            ->orderByDesc('tanggal')->orderByDesc('id')->first();
        $previousMesin = collect($previous?->mesin ?? [])->keyBy(fn (array $m): string => (string) ($m['machine_id'] ?? $m['nama']));

        $mesin = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Machine $machine): array => [
                'machine_id' => $machine->id,
                'nama' => Str::upper($machine->name),
                'level_bbm' => null,
                'tambah_bbm' => null,
                'pelumas' => null,
                'status' => $previousMesin->get((string) $machine->id)['status'] ?? null,
            ])->values()->all();

        return [
            'id' => null,
            'tanggal' => $tanggal,
            'shift' => $shift,
            'mesin' => $mesin,
            'tangki' => collect($previous?->tangki ?? [['nama' => '1', 'level_cm' => null]])->map(fn (array $t): array => ['nama' => $t['nama'], 'level_cm' => null])->values()->all(),
            'peralatan' => collect($previous?->peralatan ?? [['nama' => 'Radio HT'], ['nama' => 'Senter']])->map(fn (array $p): array => ['nama' => $p['nama'], 'ada' => false, 'jumlah' => null])->values()->all(),
            'kejadian' => [],
            'gangguan_mesin' => $previous?->gangguan_mesin,
            'catatan' => null,
            'regu_penyerah' => null,
            'penyerah_nama' => null,
            'paraf_penyerah_url' => null,
            'diserahkan_at' => null,
            'regu_penerima' => null,
            'penerima_nama' => null,
            'paraf_penerima_url' => null,
            'diterima_at' => null,
            'status' => 'baru',
            'carried_from' => $previous ? $this->label($previous) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(OperatorMutasi $mutasi): array
    {
        $disk = Storage::disk('public');

        return [
            'id' => $mutasi->id,
            'tanggal' => $mutasi->tanggal->toDateString(),
            'shift' => $mutasi->shift,
            'mesin' => $mutasi->mesin,
            'tangki' => $mutasi->tangki,
            'peralatan' => $mutasi->peralatan,
            'kejadian' => $mutasi->kejadian,
            'gangguan_mesin' => $mutasi->gangguan_mesin,
            'catatan' => $mutasi->catatan,
            'regu_penyerah' => $mutasi->regu_penyerah,
            'penyerah_nama' => $mutasi->penyerah_nama,
            'paraf_penyerah_url' => $mutasi->paraf_penyerah ? $disk->url($mutasi->paraf_penyerah) : null,
            'diserahkan_at' => $mutasi->diserahkan_at?->toIso8601String(),
            'regu_penerima' => $mutasi->regu_penerima,
            'penerima_nama' => $mutasi->penerima_nama,
            'paraf_penerima_url' => $mutasi->paraf_penerima ? $disk->url($mutasi->paraf_penerima) : null,
            'diterima_at' => $mutasi->diterima_at?->toIso8601String(),
            'status' => $this->status($mutasi),
            'carried_from' => null,
        ];
    }

    private function status(OperatorMutasi $mutasi): string
    {
        return match (true) {
            $mutasi->isReceived() => 'diterima',
            $mutasi->diserahkan_at !== null => 'diserahkan',
            default => 'draft',
        };
    }

    private function replaceParaf(OperatorMutasi $mutasi, string $column, string $dataUrl): void
    {
        $png = base64_decode(Str::after($dataUrl, 'base64,'), true);
        if ($png === false || ! str_starts_with($png, "\x89PNG") || strlen($png) > self::MAX_PARAF_BYTES) {
            throw ValidationException::withMessages(['paraf' => 'Paraf tidak valid, silakan ulangi.']);
        }

        $path = "operator-mutasi/{$mutasi->unit_id}/paraf/".Str::random(40).'.png';
        Storage::disk('public')->put($path, $png);

        if ($mutasi->{$column}) {
            Storage::disk('public')->delete($mutasi->{$column});
        }
        $mutasi->{$column} = $path;
    }

    /**
     * Date + shift of the request, defaulting to the shift running now (WITA);
     * before 08:00 the night shift still belongs to yesterday.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveSlot(Request $request): array
    {
        $now = Carbon::now(self::TIMEZONE);
        $currentShift = match (true) {
            $now->hour >= 8 && $now->hour < 16 => 'pagi',
            $now->hour >= 16 && $now->hour < 22 => 'sore',
            default => 'malam',
        };
        $currentDate = $now->hour < 8 ? $now->copy()->subDay() : $now;

        $tanggal = $request->filled('tanggal') ? Carbon::parse($request->string('tanggal')->toString())->toDateString() : $currentDate->toDateString();
        $shift = in_array($request->string('shift')->toString(), array_keys(OperatorMutasi::SHIFTS), true) ? $request->string('shift')->toString() : $currentShift;

        return [$tanggal, $shift];
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit}
     */
    private function resolveUnit(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);

        return [$units, $unit];
    }

    private function employeeOf(User $user, Unit $unit): ?Employee
    {
        return Employee::query()->where('user_id', $user->id)->where('unit_id', $unit->id)->first();
    }

    private function backTo(OperatorMutasi $mutasi): RedirectResponse
    {
        return redirect()->route('operator.mutasi.index', [
            'unit_id' => $mutasi->unit_id, 'tanggal' => $mutasi->tanggal->toDateString(), 'shift' => $mutasi->shift,
        ]);
    }

    private function label(OperatorMutasi $mutasi): string
    {
        return $mutasi->tanggal->format('d/m/Y').' shift '.$mutasi->shift;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function embed(?string $path): ?string
    {
        $disk = Storage::disk('public');

        return $path !== null && $disk->exists($path) ? 'data:image/png;base64,'.base64_encode((string) $disk->get($path)) : null;
    }
}
