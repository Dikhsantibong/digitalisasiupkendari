<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesPdmInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Pdm\PdmFormDocuments;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\PdmForms\PdmForm;
use App\Support\PdmForms\PdmForms;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One controller for every generic PdM input form ({@see PdmForms}): Checklist
 * 5S5R, Kualitas Air Pendingin, Kualitas Pelumas, Vibrasi and Kontrol
 * Material. Per-machine forms keep one document per machine (`machine_id`).
 * The PDF is resources/views/pdm/input/{form}-pdf.blade.php.
 */
class FormInputController extends Controller
{
    use HandlesPdmInput;
    use RendersReportPdf;

    public function __construct(
        private readonly PdmFormDocuments $documents,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request, string $form): Response
    {
        $definition = $this->definition($form);
        [$units, $unit, $month, $year] = $this->pdmReadTarget($request);
        [$machines, $machine] = $this->machine($definition, $unit, $request);

        return Inertia::render('pdm/input/forms/show', [
            'form' => $definition->toArray(),
            'kop_lines' => $definition->kopLines($unit->name),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'machine_id' => $machine?->id],
            'options' => $this->pdmFilterOptions($units) + [
                'machines' => $machines->map(fn (Machine $m): array => ['id' => $m->id, 'name' => $m->name])->values()->all(),
            ],
            'document' => $this->documents->load($definition, $unit, $month, $year, $this->subject($machine), $machine),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::PdmInputWrite),
        ]);
    }

    public function store(Request $request, string $form): RedirectResponse
    {
        $definition = $this->definition($form);
        [$unit, $month, $year] = $this->pdmWriteTarget($request);
        [, $machine] = $this->machine($definition, $unit, $request);

        $rules = [
            'header' => ['nullable', 'array'],
            'rows' => ['nullable', 'array'],
        ];
        foreach ($definition->fields() as $field) {
            $rules["header.{$field['key']}"] = ($field['type'] ?? 'text') === 'images'
                ? ['nullable', 'array']
                : ['nullable', 'string', ($field['type'] ?? 'text') === 'textarea' ? 'max:5000' : 'max:255'];
            if (($field['type'] ?? 'text') === 'images') {
                $rules["header.{$field['key']}.*"] = ['string', 'max:255'];
                $rules["uploads.{$field['key']}"] = ['nullable', 'array', 'max:10'];
                $rules["uploads.{$field['key']}.*"] = ['image', 'max:5120'];
            }
        }
        foreach ($definition->sections() as $section) {
            $rules["rows.{$section['key']}"] = ['nullable', 'array', 'max:200'];
            foreach ($section['columns'] as $column) {
                $rules["rows.{$section['key']}.*.{$column['key']}"] = match ($column['type'] ?? 'text') {
                    'number' => ['nullable', 'numeric'],
                    'date' => ['nullable', 'date'],
                    'select' => ['nullable', 'string', 'max:255'],
                    default => ['nullable', 'string', 'max:1000'],
                };
            }
        }
        $validated = $request->validate($rules);

        $uploads = [];
        foreach ((array) $request->file('uploads', []) as $field => $files) {
            $uploads[$field] = array_values(array_filter((array) $files));
        }

        $this->documents->save(
            $definition, $unit, $month, $year, $this->subject($machine),
            (array) ($validated['header'] ?? []), (array) ($validated['rows'] ?? []), $uploads, $request->user()->id,
        );

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan {$definition->title()} {$unit->name} {$month}/{$year}".($machine ? " ({$machine->name})" : ''),
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$definition->title()} berhasil disimpan."]);

        return back();
    }

    public function pdf(Request $request, string $form): HttpResponse
    {
        $definition = $this->definition($form);
        [, $unit, $month, $year] = $this->pdmReadTarget($request);
        [, $machine] = $this->machine($definition, $unit, $request);

        $filename = sprintf('%s_%s_%02d_%d.pdf', str_replace(' ', '_', $definition->title()), str_replace(' ', '_', $unit->name), $month, $year);

        return response($this->renderPdf($definition, $unit, $month, $year, $machine), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * The form's PDF bytes for one document.
     */
    private function renderPdf(PdmForm $definition, Unit $unit, int $month, int $year, ?Machine $machine): string
    {
        [$view, $data] = $this->pdfView($definition, $unit, $month, $year, $machine);

        return $this->renderReportPdf($view, $data, $definition->orientation());
    }

    /**
     * The form's PDF view and its data for one document (per machine for
     * per-machine forms) — shared by the PDF and the editable Laporan PdM.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(PdmForm $definition, Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return ["pdm.input.{$definition->key()}-pdf", [
            'form' => $definition,
            'unit' => $unit,
            'machine' => $machine,
            'periodLabel' => Indonesian::monthName($month).' '.$year,
            'document' => $this->documents->load($definition, $unit, $month, $year, $this->subject($machine), $machine, embedImages: true),
            ...JadwalPdf::logos(),
        ]];
    }

    private function definition(string $form): PdmForm
    {
        $definition = PdmForms::find($form);
        abort_if($definition === null, 404);

        return $definition;
    }

    /**
     * The unit's machines and the selected one (per-machine forms only).
     *
     * @return array{0: Collection<int, Machine>, 1: Machine|null}
     */
    private function machine(PdmForm $definition, Unit $unit, Request $request): array
    {
        if (! $definition->perMachine()) {
            return [new Collection, null];
        }

        $machines = Machine::query()->where('unit_id', $unit->id)->orderBy('name')->get(['id', 'name', 'type', 'unit_id']);
        $machine = $machines->firstWhere('id', $request->integer('machine_id')) ?? $machines->first();

        return [$machines, $machine];
    }

    private function subject(?Machine $machine): string
    {
        return $machine ? (string) $machine->id : '';
    }
}
