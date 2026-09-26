<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\HarClearanceValve;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Har\HarClearanceValvePdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ClearanceValveController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly HarClearanceValvePdfBuilder $pdfBuilder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganClearanceValve) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        // Mesin strictly for this unit
        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name', 'type', 'serial_number', 'capacity_kw']);

        $selectedMachineId = $request->integer('machine_id');
        $machine = $machines->firstWhere('id', $selectedMachineId) ?? $machines->first();

        // Selected test date
        $testDate = $request->input('test_date', Carbon::today()->format('Y-m-d'));

        // Look for existing saved record
        $record = null;
        if ($request->filled('record_id')) {
            $record = HarClearanceValve::query()->where('unit_id', $unit->id)->find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarClearanceValve::query()
                ->where('unit_id', $unit->id)
                ->where('machine_id', $machine->id)
                ->where('test_date', $testDate)
                ->latest()
                ->first();
        }

        // Available employees for signatories
        $managerOptions = $this->resolveManagerOptions($unit);
        $tlOptions = $this->resolveTlOptions($unit);
        $staffOptions = $this->resolveStaffOptions($unit);

        // Build preview/form data
        $inputData = $record ? $record->toArray() : [
            'test_date' => $testDate,
            'document_number' => 'FMKD-314-10.3.3.a-B3',
            'revision' => '02',
            'effective_date' => '31 Juli 2024',
            'brand' => 'MAK',
            'model_type' => $machine?->type ?? '8M 453 C',
            'serial_number' => $machine?->serial_number ?? '',
            'machine_number' => $machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #'], '', $machine->name) : '4',
            'installed_power' => $machine?->capacity_kw ?? '2800',
            'capable_power' => '',
            'rpm' => '600',
            'cylinders_count' => 8,
            'standard_ex' => '0.60 mm',
            'standard_in' => '0.30 mm',
            'standard_allowed' => null,
            'visual_inspection' => '',
            'cylinder_notes' => '',
        ];

        $viewData = $this->pdfBuilder->buildData($unit, $machine, $inputData);
        $renderedHtml = $this->pdfBuilder->renderHtml($viewData);

        // History of past records for this unit/machine
        $history = HarClearanceValve::query()
            ->where('unit_id', $unit->id)
            ->when($machine, fn ($q) => $q->where('machine_id', $machine->id))
            ->orderByDesc('test_date')
            ->take(15)
            ->get(['id', 'machine_id', 'test_date', 'document_number', 'revision', 'format', 'updated_at']);

        return Inertia::render('har/formulir/clearance-valve/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'units' => $units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'machines' => $machines->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type,
                'serial_number' => $m->serial_number,
                'capacity_kw' => $m->capacity_kw,
            ]),
            'selected_machine_id' => $machine?->id,
            'selected_test_date' => $testDate,
            'record' => $record,
            'form_data' => $viewData,
            'rendered_html' => $record && $record->format === 'html' && ! empty($record->content_html)
                ? $record->content_html
                : $renderedHtml,
            'manager_options' => $managerOptions,
            'tl_options' => $tlOptions,
            'staff_options' => $staffOptions,
            'history' => $history,
            'pdf_url' => route('har.formulir.clearance-valve.pdf', [
                'unit_id' => $unit->id,
                'machine_id' => $machine?->id,
                'test_date' => $testDate,
                'record_id' => $record?->id,
            ]),
            'can_write' => $this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganClearanceValve),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganClearanceValve), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'test_date' => ['required', 'date'],
            'document_number' => ['required', 'string', 'max:100'],
            'revision' => ['required', 'string', 'max:20'],
            'effective_date' => ['required', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model_type' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'machine_number' => ['nullable', 'string', 'max:100'],
            'installed_power' => ['nullable', 'string', 'max:100'],
            'capable_power' => ['nullable', 'string', 'max:100'],
            'rpm' => ['nullable', 'string', 'max:100'],
            'cylinders_count' => ['required', 'integer', 'between:1,32'],
            'standard_ex' => ['required', 'string', 'max:50'],
            'standard_in' => ['required', 'string', 'max:50'],
            'standard_allowed' => ['nullable', 'string'],
            'visual_inspection' => ['nullable', 'string'],
            'cylinder_notes' => ['nullable', 'string'],
            'measurements' => ['required', 'array'],
            'manager_ul_id' => ['nullable', 'integer', 'exists:employees,id'],
            'manager_ul_name' => ['nullable', 'string', 'max:150'],
            'manager_ul_title' => ['nullable', 'string', 'max:150'],
            'tl_har_id' => ['nullable', 'integer', 'exists:employees,id'],
            'tl_har_name' => ['nullable', 'string', 'max:150'],
            'tl_har_title' => ['nullable', 'string', 'max:150'],
            'staff_har_id' => ['nullable', 'integer', 'exists:employees,id'],
            'staff_har_name' => ['nullable', 'string', 'max:150'],
            'staff_har_title' => ['nullable', 'string', 'max:150'],
            'page_margin_top' => ['required', 'integer', 'between:0,50'],
            'page_margin_bottom' => ['required', 'integer', 'between:0,50'],
            'page_margin_left' => ['required', 'integer', 'between:0,50'],
            'page_margin_right' => ['required', 'integer', 'between:0,50'],
            'line_spacing' => ['required', 'string'],
            'format' => ['required', 'in:form,html'],
            'content_html' => ['nullable', 'string'],
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $record = HarClearanceValve::query()->updateOrCreate(
            [
                'unit_id' => $validated['unit_id'],
                'machine_id' => $validated['machine_id'],
                'test_date' => $validated['test_date'],
            ],
            array_merge($validated, [
                'created_by' => $user->id,
            ])
        );

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Formulir Pengukuran Clearance Valve {$unit->name} tanggal {$validated['test_date']}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir Pengukuran Clearance Valve berhasil disimpan.',
        ]);

        return back();
    }

    public function pdf(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganClearanceValve) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $unit = Unit::with('serviceUnit')->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $machine = Machine::find($request->integer('machine_id'));
        $testDate = $request->input('test_date', Carbon::today()->format('Y-m-d'));

        $record = null;
        if ($request->filled('record_id')) {
            $record = HarClearanceValve::find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarClearanceValve::query()
                ->where('unit_id', $unit->id)
                ->where('machine_id', $machine->id)
                ->where('test_date', $testDate)
                ->latest()
                ->first();
        }

        if ($record && $record->format === 'html' && ! empty($record->content_html) && ! $request->has('cylinders_count')) {
            $html = $record->content_html;
        } else {
            $data = $record ? $record->toArray() : [
                'test_date' => $testDate,
                'document_number' => $request->input('document_number', 'FMKD-314-10.3.3.a-B3'),
                'revision' => $request->input('revision', '02'),
                'effective_date' => $request->input('effective_date', '31 Juli 2024'),
                'brand' => $request->input('brand', 'MAK'),
                'model_type' => $request->input('model_type', $machine?->type ?? '8M 453 C'),
                'serial_number' => $request->input('serial_number', $machine?->serial_number ?? ''),
                'machine_number' => $request->input('machine_number', $machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #'], '', $machine->name) : '4'),
                'installed_power' => $request->input('installed_power', $machine?->capacity_kw ?? '2800'),
                'capable_power' => $request->input('capable_power', ''),
                'rpm' => $request->input('rpm', '600'),
                'cylinders_count' => $request->integer('cylinders_count', 8),
                'standard_ex' => $request->input('standard_ex', '0.60 mm'),
                'standard_in' => $request->input('standard_in', '0.30 mm'),
                'standard_allowed' => $request->input('standard_allowed'),
                'visual_inspection' => $request->input('visual_inspection', ''),
                'cylinder_notes' => $request->input('cylinder_notes', ''),
                'measurements' => $request->input('measurements', []),
                'manager_ul_id' => $request->input('manager_ul_id'),
                'manager_ul_name' => $request->input('manager_ul_name'),
                'manager_ul_title' => $request->input('manager_ul_title'),
                'tl_har_id' => $request->input('tl_har_id'),
                'tl_har_name' => $request->input('tl_har_name'),
                'tl_har_title' => $request->input('tl_har_title'),
                'staff_har_id' => $request->input('staff_har_id'),
                'staff_har_name' => $request->input('staff_har_name'),
                'staff_har_title' => $request->input('staff_har_title'),
                'page_margin_top' => $request->integer('page_margin_top', 10),
                'page_margin_bottom' => $request->integer('page_margin_bottom', 10),
                'page_margin_left' => $request->integer('page_margin_left', 12),
                'page_margin_right' => $request->integer('page_margin_right', 12),
                'line_spacing' => $request->input('line_spacing', '1.15'),
            ];

            $viewData = $this->pdfBuilder->buildData($unit, $machine, $data);
            $html = $this->pdfBuilder->renderHtml($viewData);
        }

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $filename = sprintf(
            'Formulir-Clearance-Valve-%s-%s-%s.pdf',
            str_replace(' ', '_', $unit->name),
            $machine ? str_replace(' ', '_', $machine->name) : 'Mesin',
            Carbon::parse($testDate)->format('Ymd')
        );

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    public function destroy(Request $request, HarClearanceValve $record): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganClearanceValve), 403);
        abort_unless($user->canAccessUnit($record->unit_id), 403);

        $unitName = $record->unit?->name;
        $date = $record->test_date->format('d/m/Y');
        $record->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus Formulir Pengukuran Clearance Valve {$unitName} tanggal {$date}",
            unit: $record->unit_id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data formulir berhasil dihapus.',
        ]);

        return back();
    }

    /**
     * @return array<int, array{id: int, name: string, position: string|null}>
     */
    private function resolveManagerOptions(Unit $unit): array
    {
        return $unit->employees()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('position', 'like', '%manager%')
                    ->orWhere('position', 'like', '%ph.manager%')
                    ->orWhere('position', 'like', '%kepala%')
                    ->orWhere('position', 'like', '%pj%');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'position' => $e->position])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, position: string|null}>
     */
    private function resolveTlOptions(Unit $unit): array
    {
        return $unit->employees()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('position', 'like', '%team leader%')
                    ->orWhere('position', 'like', '%tl %')
                    ->orWhere('position', 'like', '%pemeliharaan%')
                    ->orWhere('position', 'like', '%supervisor%');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'position' => $e->position])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, position: string|null}>
     */
    private function resolveStaffOptions(Unit $unit): array
    {
        return $unit->employees()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'position' => $e->position])
            ->all();
    }
}
