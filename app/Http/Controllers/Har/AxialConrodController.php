<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HarAxialConrod;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Har\HarAxialConrodPdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AxialConrodController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly HarAxialConrodPdfBuilder $pdfBuilder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganAxialConrod) ||
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
            $record = HarAxialConrod::query()->where('unit_id', $unit->id)->find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarAxialConrod::query()
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

        // Default scan alignment values (from media_1789395601139.png - Mesin #3 MAK 8M 453 AK)
        $inputData = $record ? $record->toArray() : [
            'test_date' => $testDate,
            'document_number' => 'FMKD-314-10.3.3.a-B7',
            'revision' => '03',
            'effective_date' => '31 Juli 2024',
            'brand' => 'MAK',
            'model_type' => $machine?->type ?? '8M 453 AK',
            'serial_number' => $machine?->serial_number ?? '',
            'machine_number' => $machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #'], '', $machine->name) : '3',
            'installed_power' => $machine?->capacity_kw ?? '2544',
            'capable_power' => '',
            'rpm' => '600',
            'cylinders_count' => 8,
            'torque_standard' => '750 NM',
            'standard_allowed' => '',
            'notes' => '',
            'manager_ul_name' => 'SURYADI PRATAMA',
            'manager_ul_title' => 'PH. Manager Unit PLTD '.($unit->serviceUnit?->name ?? $unit->name),
            'tl_har_name' => 'SURYADI PRATAMA',
            'tl_har_title' => 'Team Leader Pemeliharaan',
            'staff_har_name' => 'RAHMAT RAHIM FAISAL',
            'staff_har_title' => 'Staf Pemeliharaan',
        ];

        $viewData = $this->pdfBuilder->buildData($unit, $machine, $inputData);
        $renderedHtml = $this->pdfBuilder->renderHtml($viewData);

        // History of past records for this unit/machine
        $history = HarAxialConrod::query()
            ->where('unit_id', $unit->id)
            ->when($machine, fn ($q) => $q->where('machine_id', $machine->id))
            ->orderByDesc('test_date')
            ->limit(20)
            ->get(['id', 'machine_id', 'test_date', 'document_number', 'revision', 'format', 'updated_at'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'machine_id' => $item->machine_id,
                'test_date' => $item->test_date->format('Y-m-d'),
                'document_number' => $item->document_number,
                'revision' => $item->revision,
                'format' => $item->format,
                'updated_at' => $item->updated_at?->diffForHumans() ?? '',
            ]);

        return Inertia::render('har/formulir/axial-conrod/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'units' => $units,
            'machines' => $machines,
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
            'pdf_url' => route('har.formulir.axial-conrod.pdf', [
                'unit_id' => $unit->id,
                'machine_id' => $machine?->id,
                'test_date' => $testDate,
                'record_id' => $record?->id,
            ]),
            'can_write' => $this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganAxialConrod),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganAxialConrod), 403);

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
            'torque_standard' => ['required', 'string', 'max:50'],
            'standard_allowed' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
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

        $record = HarAxialConrod::query()->updateOrCreate(
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
            "Menyimpan Formulir Pemeriksaan Axial Conrod & Baut Conrod {$unit->name} tanggal {$validated['test_date']}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir Pemeriksaan Axial Conrod & Baut Conrod berhasil disimpan.',
        ]);

        return back();
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganAxialConrod), 403);

        $recordId = $request->integer('record_id');
        if ($recordId) {
            $record = HarAxialConrod::find($recordId);
            if ($record) {
                $record->delete();
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir berhasil direset ke nilai awal.',
        ]);

        return redirect()->route('har.formulir.axial-conrod.index', [
            'unit_id' => $request->integer('unit_id'),
            'machine_id' => $request->integer('machine_id'),
            'test_date' => $request->input('test_date'),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganAxialConrod) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $unit = Unit::with('serviceUnit')->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $machine = Machine::find($request->integer('machine_id'));
        $testDate = $request->input('test_date', Carbon::today()->format('Y-m-d'));

        $record = null;
        if ($request->filled('record_id')) {
            $record = HarAxialConrod::find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarAxialConrod::query()
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
                'document_number' => $request->input('document_number', 'FMKD-314-10.3.3.a-B7'),
                'revision' => $request->input('revision', '03'),
                'effective_date' => $request->input('effective_date', '31 Juli 2024'),
                'brand' => $request->input('brand', 'MAK'),
                'model_type' => $request->input('model_type', $machine?->type ?? '8M 453 AK'),
                'serial_number' => $request->input('serial_number', $machine?->serial_number ?? ''),
                'machine_number' => $request->input('machine_number', $machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #'], '', $machine->name) : '3'),
                'installed_power' => $request->input('installed_power', $machine?->capacity_kw ?? '2544'),
                'capable_power' => $request->input('capable_power', ''),
                'rpm' => $request->input('rpm', '600'),
                'cylinders_count' => (int) $request->input('cylinders_count', 8),
                'torque_standard' => $request->input('torque_standard', '750 NM'),
                'standard_allowed' => $request->input('standard_allowed', ''),
                'notes' => $request->input('notes', ''),
                'manager_ul_id' => $request->integer('manager_ul_id') ?: null,
                'manager_ul_name' => $request->input('manager_ul_name'),
                'manager_ul_title' => $request->input('manager_ul_title'),
                'tl_har_id' => $request->integer('tl_har_id') ?: null,
                'tl_har_name' => $request->input('tl_har_name'),
                'tl_har_title' => $request->input('tl_har_title'),
                'staff_har_id' => $request->integer('staff_har_id') ?: null,
                'staff_har_name' => $request->input('staff_har_name'),
                'staff_har_title' => $request->input('staff_har_title'),
                'page_margin_top' => (int) $request->input('page_margin_top', 10),
                'page_margin_bottom' => (int) $request->input('page_margin_bottom', 10),
                'page_margin_left' => (int) $request->input('page_margin_left', 12),
                'page_margin_right' => (int) $request->input('page_margin_right', 12),
                'line_spacing' => $request->input('line_spacing', '1.15'),
            ];

            // Override with any live query parameters
            foreach ($request->only([
                'document_number', 'revision', 'effective_date', 'brand', 'model_type',
                'serial_number', 'machine_number', 'installed_power', 'capable_power',
                'rpm', 'cylinders_count', 'torque_standard', 'standard_allowed', 'notes',
                'manager_ul_id', 'manager_ul_name', 'manager_ul_title',
                'tl_har_id', 'tl_har_name', 'tl_har_title',
                'staff_har_id', 'staff_har_name', 'staff_har_title',
                'page_margin_top', 'page_margin_bottom', 'page_margin_left', 'page_margin_right',
                'line_spacing',
            ]) as $key => $val) {
                if ($val !== null) {
                    $data[$key] = $val;
                }
            }

            if ($request->has('measurements')) {
                $rawMeas = $request->input('measurements');
                if (is_string($rawMeas)) {
                    $data['measurements'] = json_decode($rawMeas, true) ?? [];
                } elseif (is_array($rawMeas)) {
                    $data['measurements'] = $rawMeas;
                }
            }

            $viewData = $this->pdfBuilder->buildData($unit, $machine, $data);
            $html = $this->pdfBuilder->renderHtml($viewData);
        }

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = sprintf(
            'Formulir_Pemeriksaan_Axial_Conrod_%s_%s.pdf',
            str_replace(' ', '_', $unit->name),
            $testDate
        );

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function destroy(HarAxialConrod $axialConrod): RedirectResponse
    {
        $user = request()->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganAxialConrod), 403);

        $unitId = $axialConrod->unit_id;
        $axialConrod->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus formulir pemeriksaan axial conrod ID: {$axialConrod->id}",
            $axialConrod,
            unit: $unitId,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Catatan formulir berhasil dihapus.',
        ]);

        return back();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveManagerOptions(Unit $unit): array
    {
        return Employee::query()
            ->where(function ($q) use ($unit) {
                $q->where('unit_id', $unit->id)
                    ->orWhere('position', 'like', '%manager%');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'signature_path'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'nip' => $e->nip,
                'position' => $e->position,
                'has_signature' => ! empty($e->signature_path),
            ])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveTlOptions(Unit $unit): array
    {
        return Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'signature_path'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'nip' => $e->nip,
                'position' => $e->position,
                'has_signature' => ! empty($e->signature_path),
            ])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveStaffOptions(Unit $unit): array
    {
        return Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'signature_path'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'nip' => $e->nip,
                'position' => $e->position,
                'has_signature' => ! empty($e->signature_path),
            ])
            ->toArray();
    }
}
