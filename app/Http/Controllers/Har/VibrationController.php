<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarVibration;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Har\HarVibrationPdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class VibrationController extends Controller
{
    public function __construct(
        private readonly HarVibrationPdfBuilder $pdfBuilder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
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
            $record = HarVibration::query()->where('unit_id', $unit->id)->find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarVibration::query()
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
            'document_number' => 'FMKD-314-10.3.3.a-B10',
            'revision' => '03',
            'effective_date' => '31 Juli 2024',
            'brand' => $machine?->brand ?? 'MAK',
            'model_type' => $machine?->type ?? '8M 453 C',
            'installed_power' => $machine?->capacity_kw ?? '2800',
            'capable_power' => '1500 kW',
            'serial_number' => $machine?->serial_number ?? '',
            'machine_number' => $machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #', '#'], '', $machine->name) : '4',
            'rpm' => '600',
            'measurements' => HarVibration::sampleScanMeasurements(),
            'standard_text' => '',
            'max_text' => '',
            'conclusion_text' => '',
        ];

        $viewData = $this->pdfBuilder->buildData($unit, $machine, $inputData);
        $renderedHtml = $this->pdfBuilder->renderHtml($viewData);

        // History of past records for this unit/machine
        $history = HarVibration::query()
            ->where('unit_id', $unit->id)
            ->when($machine, fn ($q) => $q->where('machine_id', $machine->id))
            ->orderByDesc('test_date')
            ->take(15)
            ->get(['id', 'machine_id', 'test_date', 'document_number', 'revision', 'format', 'updated_at']);

        return Inertia::render('har/formulir/vibration/index', [
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
            'pdf_url' => route('har.formulir.vibration.pdf', [
                'unit_id' => $unit->id,
                'machine_id' => $machine?->id,
                'test_date' => $testDate,
                'record_id' => $record?->id,
            ]),
            'sample_scan_measurements' => HarVibration::sampleScanMeasurements(),
            'default_measurements' => HarVibration::defaultMeasurements(),
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'test_date' => ['required', 'date'],
            'document_number' => ['required', 'string', 'max:100'],
            'revision' => ['required', 'string', 'max:20'],
            'effective_date' => ['required', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model_type' => ['nullable', 'string', 'max:100'],
            'installed_power' => ['nullable', 'string', 'max:100'],
            'capable_power' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'machine_number' => ['nullable', 'string', 'max:50'],
            'rpm' => ['nullable', 'string', 'max:50'],
            'measurements' => ['required', 'array'],
            'standard_text' => ['nullable', 'string'],
            'max_text' => ['nullable', 'string'],
            'conclusion_text' => ['nullable', 'string'],
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

        // Auto calculate averages if missing
        foreach ($validated['measurements'] as $idx => $m) {
            if (empty($m['v_avg']) && ! empty($m['v_max']) && ! empty($m['v_min'])) {
                $validated['measurements'][$idx]['v_avg'] = HarVibrationPdfBuilder::calculateAvg($m['v_max'], $m['v_min']);
            }
            if (empty($m['h_avg']) && ! empty($m['h_max']) && ! empty($m['h_min'])) {
                $validated['measurements'][$idx]['h_avg'] = HarVibrationPdfBuilder::calculateAvg($m['h_max'], $m['h_min']);
            }
        }

        $record = HarVibration::query()->updateOrCreate(
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
            "Menyimpan Formulir Pengukuran Vibrasi {$unit->name} tanggal {$validated['test_date']}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir Pengukuran Vibrasi berhasil disimpan.',
        ]);

        return redirect()->route('har.formulir.vibration.index', [
            'unit_id' => $record->unit_id,
            'machine_id' => $record->machine_id,
            'test_date' => $record->test_date->format('Y-m-d'),
            'record_id' => $record->id,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unitId = $request->integer('unit_id');
        $machineId = $request->integer('machine_id');
        $testDate = $request->input('test_date', Carbon::today()->format('Y-m-d'));

        $record = HarVibration::query()
            ->where('unit_id', $unitId)
            ->where('machine_id', $machineId)
            ->where('test_date', $testDate)
            ->first();

        if ($record) {
            $record->delete();

            $this->activityLogger->log(
                ActivityEvent::Deleted,
                "Mereset Formulir Pengukuran Vibrasi unit ID {$unitId} tanggal {$testDate}",
                unit: $unitId,
            );
        }

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => 'Nilai formulir vibrasi telah direset ke format standar.',
        ]);

        return redirect()->route('har.formulir.vibration.index', [
            'unit_id' => $unitId,
            'machine_id' => $machineId,
            'test_date' => $testDate,
        ]);
    }

    public function pdf(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $machineId = $request->integer('machine_id');
        $machine = Machine::query()->where('unit_id', $unit->id)->find($machineId);

        $testDate = $request->input('test_date', Carbon::today()->format('Y-m-d'));

        $record = null;
        if ($request->filled('record_id')) {
            $record = HarVibration::find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarVibration::query()
                ->where('unit_id', $unit->id)
                ->where('machine_id', $machine->id)
                ->where('test_date', $testDate)
                ->latest()
                ->first();
        }

        if ($record && $record->format === 'html' && ! empty($record->content_html) && ! $request->has('measurements')) {
            $html = $record->content_html;
        } else {
            $baseData = $record ? $record->toArray() : [];
            $requestData = array_filter($request->only([
                'test_date', 'document_number', 'revision', 'effective_date',
                'brand', 'model_type', 'installed_power', 'capable_power',
                'serial_number', 'machine_number', 'rpm', 'measurements',
                'standard_text', 'max_text', 'conclusion_text',
                'manager_ul_id', 'manager_ul_name', 'manager_ul_title',
                'tl_har_id', 'tl_har_name', 'tl_har_title',
                'staff_har_id', 'staff_har_name', 'staff_har_title',
                'page_margin_top', 'page_margin_bottom', 'page_margin_left', 'page_margin_right',
                'line_spacing',
            ]), fn ($val) => ! is_null($val));

            $defaultData = [
                'test_date' => $testDate,
                'document_number' => 'FMKD-314-10.3.3.a-B10',
                'revision' => '03',
                'effective_date' => '31 Juli 2024',
                'brand' => $machine?->brand ?? 'MAK',
                'model_type' => $machine?->type ?? '8M 453 C',
                'installed_power' => $machine?->capacity_kw ?? '2800',
                'capable_power' => '1500 kW',
                'serial_number' => $machine?->serial_number ?? '',
                'machine_number' => $machine ? str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #', '#'], '', $machine->name) : '4',
                'rpm' => '600',
                'measurements' => HarVibration::sampleScanMeasurements(),
                'standard_text' => '',
                'max_text' => '',
                'conclusion_text' => '',
                'page_margin_top' => 8,
                'page_margin_bottom' => 8,
                'page_margin_left' => 10,
                'page_margin_right' => 10,
                'line_spacing' => '1.1',
            ];

            $data = array_merge($defaultData, $baseData, $requestData);

            $viewData = $this->pdfBuilder->buildData($unit, $machine, $data);
            $html = $this->pdfBuilder->renderHtml($viewData);
        }

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $filename = sprintf(
            'Formulir-Pengukuran-Vibrasi-%s-%s-%s.pdf',
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

    public function destroy(Request $request, HarVibration $record): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($record->unit_id), 403);

        $unitName = $record->unit?->name;
        $date = $record->test_date->format('d/m/Y');

        $record->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus Formulir Pengukuran Vibrasi {$unitName} tanggal {$date}",
            unit: $record->unit_id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data formulir vibrasi berhasil dihapus.',
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
                    ->orWhere('position', 'like', '%plh%')
                    ->orWhere('position', 'like', '%kepala%');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position,
            ])
            ->toArray();
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
                    ->orWhere('position', 'like', '%supervisor%')
                    ->orWhere('position', 'like', '%pemeliharaan%');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'position'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position,
            ])
            ->toArray();
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
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position,
            ])
            ->toArray();
    }
}
