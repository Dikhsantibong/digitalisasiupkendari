<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiChecklistCommissioningMesin;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\JadwalPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistCommissioningMesinController extends Controller
{
    use AuthorizesFieldInput, RendersReportPdf;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganChecklistCommissioning) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $tanggal = $request->string('tanggal')->trim()->toString() ?: $now->format('Y-m-d');

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $machineOptions = $machines->map(fn ($m): array => ['id' => $m->id, 'name' => $m->name])->all();
        if (empty($machineOptions)) {
            $machineOptions = [
                ['id' => 1, 'name' => 'Cummins #8'],
                ['id' => 2, 'name' => 'MESIN#01'],
                ['id' => 3, 'name' => 'MESIN#02'],
            ];
        }

        $selectedMachineId = $request->integer('machine_id') ?: ($machineOptions[0]['id'] ?? null);
        $selectedMachineName = $request->string('nama_mesin')->trim()->toString();
        if ($selectedMachineName === '') {
            $matched = collect($machineOptions)->firstWhere('id', $selectedMachineId);
            $selectedMachineName = $matched ? $matched['name'] : ($machineOptions[0]['name'] ?? 'Cummins #8');
        }

        $record = OperasiChecklistCommissioningMesin::query()
            ->where('unit_id', $unit->id)
            ->where('tanggal', $tanggal)
            ->where(function ($q) use ($selectedMachineId, $selectedMachineName): void {
                if ($selectedMachineId) {
                    $q->where('machine_id', $selectedMachineId)
                        ->orWhere('nama_mesin', $selectedMachineName);
                } else {
                    $q->where('nama_mesin', $selectedMachineName);
                }
            })
            ->first();

        $rows = $record?->rows ?: OperasiChecklistCommissioningMesin::DEFAULT_ROWS;
        $catatan = $record?->catatan ?? 'disesuaikan dengan kondisi peralatan unit/sentral kit';

        return Inertia::render('operasi/input/checklist-commissioning-mesin/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'tanggal' => $tanggal,
                'machine_id' => $selectedMachineId,
                'nama_mesin' => $selectedMachineName,
            ],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'machines' => $machineOptions,
                'status_options' => array_keys(OperasiChecklistCommissioningMesin::STATUS_OPTIONS),
            ],
            'rows' => $rows,
            'catatan' => $catatan,
            'can_write' => $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganChecklistCommissioning),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganChecklistCommissioning), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'machine_id' => ['nullable', 'integer'],
            'nama_mesin' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'rows' => ['required', 'array'],
            'rows.*.no' => ['nullable'],
            'rows.*.section' => ['required', 'string', 'max:255'],
            'rows.*.kegiatan' => ['required', 'string', 'max:500'],
            'rows.*.status' => ['nullable', 'string', 'max:50'],
            'rows.*.pic' => ['nullable', 'string', 'max:255'],
            'rows.*.paraf' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $tanggal = $validated['tanggal'];
        $namaMesin = trim($validated['nama_mesin']);
        $machineId = ! empty($validated['machine_id']) ? (int) $validated['machine_id'] : null;

        DB::transaction(function () use ($unit, $tanggal, $namaMesin, $machineId, $validated, $user): void {
            OperasiChecklistCommissioningMesin::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'tanggal' => $tanggal,
                    'nama_mesin' => $namaMesin,
                ],
                [
                    'machine_id' => $machineId,
                    'rows' => $validated['rows'],
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Checklist Commissioning Test Mesin {$namaMesin} - {$unit->name} ({$tanggal})",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Checklist Commissioning Test Mesin berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganChecklistCommissioning) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $tanggal = $request->string('tanggal')->trim()->toString() ?: Carbon::now()->format('Y-m-d');
        $machineId = $request->integer('machine_id') ?: null;
        $namaMesin = $request->string('nama_mesin')->trim()->toString();

        [$view, $data] = $this->pdfView($unit, $tanggal, $machineId, $namaMesin);

        $safeUnit = str_replace(' ', '_', $unit->name);
        $safeMesin = str_replace(' ', '_', $data['namaMesin']);
        $filename = "Checklist_Commissioning_Mesin_{$safeUnit}_{$safeMesin}_{$tanggal}.pdf";

        return $this->streamReportPdf($request, $view, $data, $filename, 'landscape');
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, string $tanggal, ?int $machineId = null, string $namaMesin = ''): array
    {
        if ($namaMesin === '') {
            $machine = $machineId ? Machine::query()->find($machineId) : null;
            $namaMesin = $machine?->name ?? 'Cummins #8';
        }

        $record = OperasiChecklistCommissioningMesin::query()
            ->where('unit_id', $unit->id)
            ->where('tanggal', $tanggal)
            ->where(function ($q) use ($machineId, $namaMesin): void {
                if ($machineId) {
                    $q->where('machine_id', $machineId)
                        ->orWhere('nama_mesin', $namaMesin);
                } else {
                    $q->where('nama_mesin', $namaMesin);
                }
            })
            ->first();

        $rows = $record?->rows ?: OperasiChecklistCommissioningMesin::DEFAULT_ROWS;
        $catatan = $record?->catatan ?? 'disesuaikan dengan kondisi peralatan unit/sentral kit';

        $parsedDate = Carbon::parse($tanggal);
        $formattedDate = $parsedDate->isoFormat('D MMMM Y');

        return ['operasi.input.checklist-commissioning-mesin-pdf', [
            'unit' => $unit,
            'tanggal' => $tanggal,
            'formattedDate' => $formattedDate,
            'namaMesin' => $namaMesin,
            'rows' => $rows,
            'statusOptions' => array_keys(OperasiChecklistCommissioningMesin::STATUS_OPTIONS),
            'catatan' => $catatan,
            ...JadwalPdf::logos(),
        ]];
    }
}
