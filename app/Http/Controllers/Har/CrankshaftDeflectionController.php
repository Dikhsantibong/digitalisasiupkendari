<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HarCrankshaftDeflection;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Har\HarCrankshaftDeflectionPdfBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CrankshaftDeflectionController extends Controller
{
    public function __construct(
        private readonly HarCrankshaftDeflectionPdfBuilder $pdfBuilder,
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
            $record = HarCrankshaftDeflection::query()->where('unit_id', $unit->id)->find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarCrankshaftDeflection::query()
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
            'document_number' => 'FMKD-314-10.3.3.a-B2',
            'revision' => '02',
            'effective_date' => '31 Juli 2024',
            'brand' => 'MAK',
            'model_type' => $machine?->type ?? '8M 453 AK',
            'serial_number' => $machine?->serial_number ?? '',
            'machine_number' => $machine ? str_replace('MIRRLEES #', '', $machine->name) : '1',
            'installed_power' => $machine?->capacity_kw ?? '2544',
            'capable_power' => '',
            'rpm' => '600',
            'cylinders_count' => 8,
            'standard_min' => '-0.06',
            'standard_max' => '+0.08',
            'standard_allowed' => 'Min : -0.06, Max : +0.08',
            'visual_inspection' => '',
            'cylinder_notes' => '',
        ];

        $viewData = $this->pdfBuilder->buildData($unit, $machine, $inputData);
        $renderedHtml = $this->pdfBuilder->renderHtml($viewData);

        // History of past records for this unit/machine
        $history = HarCrankshaftDeflection::query()
            ->where('unit_id', $unit->id)
            ->when($machine, fn ($q) => $q->where('machine_id', $machine->id))
            ->orderByDesc('test_date')
            ->take(15)
            ->get(['id', 'machine_id', 'test_date', 'document_number', 'revision', 'format', 'updated_at']);

        return Inertia::render('har/formulir/crankshaft-deflection/index', [
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
            'pdf_url' => route('har.formulir.crankshaft-deflection.pdf', [
                'unit_id' => $unit->id,
                'machine_id' => $machine?->id,
                'test_date' => $testDate,
                'record_id' => $record?->id,
            ]),
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
            'serial_number' => ['nullable', 'string', 'max:100'],
            'machine_number' => ['nullable', 'string', 'max:100'],
            'installed_power' => ['nullable', 'string', 'max:100'],
            'capable_power' => ['nullable', 'string', 'max:100'],
            'rpm' => ['nullable', 'string', 'max:100'],
            'cylinders_count' => ['required', 'integer', 'between:1,32'],
            'standard_min' => ['required', 'string', 'max:50'],
            'standard_max' => ['required', 'string', 'max:50'],
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

        $record = HarCrankshaftDeflection::query()->updateOrCreate(
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
            "Menyimpan Formulir Pengukuran Defleksi Crankshaft {$unit->name} tanggal {$validated['test_date']}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir Pengukuran Defleksi Crankshaft berhasil disimpan.',
        ]);

        return back();
    }

    public function pdf(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $unit = Unit::with('serviceUnit')->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $machine = Machine::find($request->integer('machine_id'));
        $testDate = $request->input('test_date', Carbon::today()->format('Y-m-d'));

        $record = null;
        if ($request->filled('record_id')) {
            $record = HarCrankshaftDeflection::find($request->integer('record_id'));
        }
        if (! $record && $machine) {
            $record = HarCrankshaftDeflection::query()
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
                'document_number' => 'FMKD-314-10.3.3.a-B2',
                'revision' => '02',
                'effective_date' => '31 Juli 2024',
                'brand' => 'MAK',
                'model_type' => $machine?->type ?? '8M 453 AK',
                'serial_number' => $machine?->serial_number ?? '',
                'machine_number' => $machine ? str_replace('MIRRLEES #', '', $machine->name) : '1',
                'installed_power' => $machine?->capacity_kw ?? '2544',
                'capable_power' => '',
                'rpm' => '600',
                'cylinders_count' => 8,
                'standard_min' => '-0.06',
                'standard_max' => '+0.08',
                'standard_allowed' => 'Min : -0.06, Max : +0.08',
                'visual_inspection' => '',
                'cylinder_notes' => '',
            ];

            // Live overrides from preview request query
            if ($request->filled('cylinders_count')) {
                $data['cylinders_count'] = (int) $request->integer('cylinders_count');
            }
            if ($request->filled('standard_min')) {
                $data['standard_min'] = $request->input('standard_min');
            }
            if ($request->filled('standard_max')) {
                $data['standard_max'] = $request->input('standard_max');
            }
            if ($request->filled('page_margin_top')) {
                $data['page_margin_top'] = $request->integer('page_margin_top');
            }
            if ($request->filled('page_margin_bottom')) {
                $data['page_margin_bottom'] = $request->integer('page_margin_bottom');
            }
            if ($request->filled('page_margin_left')) {
                $data['page_margin_left'] = $request->integer('page_margin_left');
            }
            if ($request->filled('page_margin_right')) {
                $data['page_margin_right'] = $request->integer('page_margin_right');
            }
            if ($request->filled('line_spacing')) {
                $data['line_spacing'] = $request->input('line_spacing');
            }

            $viewData = $this->pdfBuilder->buildData($unit, $machine, $data);
            $html = $this->pdfBuilder->renderHtml($viewData);
        }

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $safeDate = Carbon::parse($testDate)->format('Ymd');
        $filename = "Defleksi-Crankshaft-{$unit->name}-{$safeDate}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    public function destroy(HarCrankshaftDeflection $crankshaftDeflection): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($crankshaftDeflection->unit_id), 403);

        $crankshaftDeflection->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir Pengukuran Defleksi Crankshaft berhasil dihapus.',
        ]);

        return back();
    }

    /**
     * Resolve Manager UL options based on ServiceUnit and Manager positions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveManagerOptions(Unit $unit): array
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->where(function ($q) use ($unit) {
                if ($unit->service_unit_id) {
                    $q->where('service_unit_id', $unit->service_unit_id);
                }
                $q->orWhere('position', 'like', '%manager%')
                  ->orWhere('position', 'like', '%manajer%');
            })
            ->orderBy('name')
            ->get();

        return $employees->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'nip' => $e->nip,
            'position' => $e->position,
            'has_signature' => ! empty($e->signature_path),
        ])->all();
    }

    /**
     * Resolve Team Leader Pemeliharaan options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveTlOptions(Unit $unit): array
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->where(function ($q) use ($unit) {
                $q->where('unit_id', $unit->id);
                if ($unit->service_unit_id) {
                    $q->orWhere('service_unit_id', $unit->service_unit_id);
                }
            })
            ->orderByRaw("CASE WHEN position LIKE '%pemeliharaan%' OR position LIKE '%tl%' OR position LIKE '%team leader%' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return $employees->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'nip' => $e->nip,
            'position' => $e->position,
            'has_signature' => ! empty($e->signature_path),
        ])->all();
    }

    /**
     * Resolve Staff Pemeliharaan options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveStaffOptions(Unit $unit): array
    {
        $employees = Employee::query()
            ->where('is_active', true)
            ->where(function ($q) use ($unit) {
                $q->where('unit_id', $unit->id);
                if ($unit->service_unit_id) {
                    $q->orWhere('service_unit_id', $unit->service_unit_id);
                }
            })
            ->orderByRaw("CASE WHEN position LIKE '%staf%' OR position LIKE '%staff%' OR position LIKE '%teknisi%' OR position LIKE '%operator%' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return $employees->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'nip' => $e->nip,
            'position' => $e->position,
            'has_signature' => ! empty($e->signature_path),
        ])->all();
    }
}
