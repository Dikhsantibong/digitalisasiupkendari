<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarLembarMeta;
use App\Models\HarLembarRow;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\HarLembar\HarLembar;
use App\Support\HarLembar\HarLembars;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lembar matriks Modul HAR yang didefinisikan di App\Support\HarLembar —
 * Jadwal Inventarisasi Tools & Material, Jadwal Pemeriksaan Instalasi
 * Blackstart (tahunan), Laporan Patrol Check Pemeliharaan (per mesin).
 * Tiap lembar punya halaman sendiri: resources/js/pages/har/{menu}/{key}/index.tsx.
 */
class LembarController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request, string $lembar): Response
    {
        $definition = $this->definition($lembar);
        $user = $request->user();
        $this->authorizeView($user);
        [$units, $unit, $month, $year] = $this->target($request);
        [$machines, $machine] = $this->machine($definition, $unit, $request);

        $saved = $this->savedRows($definition, $unit, $month, $year, $machine);
        $rows = $saved->isNotEmpty() ? $this->present($saved) : $definition->defaultRows();

        return Inertia::render("har/{$definition->menu()}/{$definition->key()}/index", [
            'lembar' => $definition->toArray($month, $year),
            'kop_lines' => $definition->kopLines($unit->name, $month, $year),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'machine_id' => $machine?->id],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range((int) now()->year - 3, (int) now()->year + 1),
                'machines' => $machines->map(fn (Machine $m): array => ['id' => $m->id, 'name' => $m->name])->values()->all(),
            ],
            'rows' => $rows,
            'catatan' => (string) $this->meta($definition, $unit, $month, $year, $machine)?->catatan,
            'summary' => $saved->isNotEmpty() ? $definition->summary($this->present($saved), $month, $year) : null,
            'has_saved' => $saved->isNotEmpty(),
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request, string $lembar): RedirectResponse
    {
        $definition = $this->definition($lembar);
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'machine_id' => [$definition->perMachine() ? 'required' : 'nullable', 'integer'],
            'rows' => ['present', 'array', 'max:300'],
            'rows.*' => ['array'],
            'rows.*.section' => ['nullable', 'string', 'max:50'],
            'rows.*.fields' => ['nullable', 'array'],
            'rows.*.fields.*' => ['nullable', 'max:255'],
            'rows.*.cells' => ['nullable', 'array'],
            'catatan' => ['nullable', 'string', 'max:3000'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $machine = $definition->perMachine()
            ? Machine::query()->where('unit_id', $unit->id)->findOrFail((int) $validated['machine_id'])
            : null;
        $period = $this->period($definition, $unit, $month, $year, $machine);

        $clean = array_values(array_filter(array_map(
            fn (array $row): ?array => $definition->sanitize($row, $month, $year),
            $validated['rows'],
        )));

        DB::transaction(function () use ($period, $clean, $definition, $validated, $user): void {
            HarLembarRow::query()->where($period)->delete();
            foreach ($clean as $index => $row) {
                HarLembarRow::query()->create([...$period, ...$row, 'sort_order' => $index, 'input_by' => $user->id]);
            }

            if ($definition->noteLabel() !== null) {
                HarLembarMeta::query()->updateOrCreate($period, ['catatan' => $validated['catatan'] ?? null, 'input_by' => $user->id]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan {$definition->title()} {$unit->name} ".($definition->yearly() ? $year : Indonesian::monthName($month)." {$year}").($machine ? " ({$machine->name})" : ''),
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$definition->title()} berhasil disimpan."]);

        return back();
    }

    public function pdf(Request $request, string $lembar): HttpResponse
    {
        $definition = $this->definition($lembar);
        $this->authorizeView($request->user());
        [, $unit, $month, $year] = $this->target($request);
        [, $machine] = $this->machine($definition, $unit, $request);
        [$view, $data] = $this->pdfView($definition, $unit, $month, $year, $machine);

        $filename = sprintf('%s_%s_%s.pdf', str_replace(' ', '_', $definition->title()), str_replace(' ', '_', $unit->name), $definition->yearly() ? $year : sprintf('%02d_%d', $month, $year));

        return response(Pdf::loadView($view, $data)->setPaper('a4', $definition->orientation())->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * The PDF view & data of one document — reusable by the Laporan HAR.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(HarLembar $definition, Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        $saved = $this->present($this->savedRows($definition, $unit, $month, $year, $machine));

        return ['har.lembar.pdf', [
            'lembar' => $definition,
            'spec' => $definition->toArray($month, $year),
            'kopLines' => $definition->kopLines($unit->name, $month, $year),
            'unit' => $unit,
            'machine' => $machine,
            'rows' => $saved !== [] ? $saved : $definition->defaultRows(),
            'catatan' => (string) $this->meta($definition, $unit, $month, $year, $machine)?->catatan,
            'summary' => $saved !== [] ? $definition->summary($saved, $month, $year) : null,
            ...JadwalPdf::logos(),
        ]];
    }

    private function definition(string $key): HarLembar
    {
        return HarLembars::find($key) ?? abort(404);
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

    /**
     * The unit's machines and the selected one (per-machine lembar only).
     *
     * @return array{0: Collection<int, Machine>, 1: Machine|null}
     */
    private function machine(HarLembar $definition, Unit $unit, Request $request): array
    {
        if (! $definition->perMachine()) {
            return [new Collection, null];
        }

        $machines = Machine::query()->where('unit_id', $unit->id)->orderBy('name')->get(['id', 'name', 'unit_id']);

        return [$machines, $machines->firstWhere('id', $request->integer('machine_id')) ?? $machines->first()];
    }

    /**
     * @return array{unit_id: int, lembar: string, year: int, month: int, subject: string}
     */
    private function period(HarLembar $definition, Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return [
            'unit_id' => $unit->id,
            'lembar' => $definition->key(),
            'year' => $year,
            'month' => $definition->yearly() ? 0 : $month,
            'subject' => $machine ? (string) $machine->id : '',
        ];
    }

    /**
     * @return Collection<int, HarLembarRow>
     */
    private function savedRows(HarLembar $definition, Unit $unit, int $month, int $year, ?Machine $machine): Collection
    {
        if ($definition->perMachine() && $machine === null) {
            return new Collection;
        }

        return HarLembarRow::query()->where($this->period($definition, $unit, $month, $year, $machine))->orderBy('sort_order')->orderBy('id')->get();
    }

    private function meta(HarLembar $definition, Unit $unit, int $month, int $year, ?Machine $machine): ?HarLembarMeta
    {
        return $definition->noteLabel() === null || ($definition->perMachine() && $machine === null)
            ? null
            : HarLembarMeta::query()->where($this->period($definition, $unit, $month, $year, $machine))->first();
    }

    /**
     * @param  Collection<int, HarLembarRow>  $rows
     * @return list<array{section: string|null, fields: array<string, mixed>, cells: array<string, array<string, string>>}>
     */
    private function present(Collection $rows): array
    {
        return $rows->map(fn (HarLembarRow $row): array => [
            'section' => $row->section,
            'fields' => $row->fields,
            'cells' => $row->cells,
        ])->values()->all();
    }
}
