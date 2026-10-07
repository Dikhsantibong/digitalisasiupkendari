<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiTug;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\TugDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — TUG 9 (Rekap Bon Pemakaian Energi Primer) per mesin,
 * shared by TUG Pelumas and TUG BBM. The amounts come from the month's sheet
 * (see {@see TugDocument}); the page edits each machine's TUG header and
 * prints it (one machine, or every machine of the unit at once).
 */
abstract class TugController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(protected readonly ActivityLogger $activityLogger) {}

    abstract protected function document(): TugDocument;

    /** The Inertia page, e.g. pengusahaan/operasi/tug-pelumas/index. */
    abstract protected function page(): string;

    /** The title used in file names and the activity log, e.g. "TUG 9 Pelumas". */
    abstract protected function title(): string;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);

        $machines = $this->machines($unit);
        $machine = $machines->firstWhere('id', (int) $request->integer('machine_id')) ?? $machines->first();
        $documents = $machines->mapWithKeys(fn (Machine $m): array => [$m->id => $this->document()->build($unit, $m, $month, $year)]);

        return Inertia::render($this->page(), [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'machine_id' => $machine?->id],
            'machines' => $machines->map(fn (Machine $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'serial_number' => $m->serial_number,
                'total' => $documents[$m->id]['grand_total'],
                'has_tug' => $documents[$m->id]['header_saved'],
            ])->values(),
            'tug' => $machine ? $documents[$machine->id] : null,
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'id')->where('unit_id', $request->integer('unit_id'))],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'nomor' => ['nullable', 'string', 'max:100'],
            'pekerjaan' => ['required', 'string', 'max:100'],
            'no_spk' => ['nullable', 'string', 'max:100'],
            'cost_center' => ['nullable', 'string', 'max:50'],
            'kode_perkiraan' => ['nullable', 'string', 'max:50'],
            'tanggal_dokumen' => ['nullable', 'date'],
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $tug = OperasiTug::query()->updateOrCreate(
            [
                'unit_id' => $unit->id,
                'machine_id' => (int) $validated['machine_id'],
                'jenis' => $this->document()->jenis(),
                'month' => (int) $validated['month'],
                'year' => (int) $validated['year'],
            ],
            [
                'nomor' => $validated['nomor'] ?? null,
                'pekerjaan' => $validated['pekerjaan'],
                'no_spk' => $validated['no_spk'] ?? null,
                'cost_center' => $validated['cost_center'] ?? null,
                'kode_perkiraan' => $validated['kode_perkiraan'] ?? null,
                'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? null,
                'input_by' => $user->id,
            ],
        );

        $this->activityLogger->log(
            $tug->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "{$this->title()} {$tug->machine->name} {$tug->month}/{$tug->year} disimpan untuk {$unit->name}",
            $tug,
            unit: $unit->id,
        );

        return back()->with('success', "Kepala {$this->title()} berhasil disimpan.");
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);
        [$month, $year] = $this->period($request);

        $machines = $this->machines($unit);
        if (! $request->boolean('all')) {
            $machines = $machines->where('id', (int) $request->integer('machine_id'))->values();
            abort_if($machines->isEmpty(), 404);
        }

        $pdf = Pdf::loadView('operasi.tug.pdf', [
            'unit' => $unit,
            'jenis' => $this->document()->jenis(),
            'documents' => $machines->map(fn (Machine $machine): array => $this->document()->build($unit, $machine, $month, $year))->all(),
            'logo' => $this->logo(),
        ])->setPaper('a4', 'portrait');

        $name = $request->boolean('all') ? 'Semua_Mesin' : str_replace(['#', ' '], ['', '_'], $machines->first()->name);
        $file = str_replace(' ', '_', $this->title());
        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("{$file}_{$unit->name}_{$name}_{$month}_{$year}.pdf");
    }

    protected function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite);
    }

    /**
     * @return Collection<int, Machine>
     */
    private function machines(Unit $unit): Collection
    {
        return Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'unit_id', 'name', 'serial_number']);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            max(2020, min(2100, (int) ($request->integer('year') ?: $now->year))),
        ];
    }

    private function logo(): ?string
    {
        $path = public_path('logo/sidebar-logo.png');

        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
