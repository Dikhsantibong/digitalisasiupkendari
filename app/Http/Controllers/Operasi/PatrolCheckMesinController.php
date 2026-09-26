<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PatrolCheckMesinController extends Controller
{
    use AuthorizesFieldInput;
    use RendersReportPdf;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(($this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganPatrolCheckMesin) || $user->hasPermissionTo(PermissionName::OperasiLaporanView)), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);

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

        $record = OperasiPatrolCheckMesin::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where(function ($q) use ($selectedMachineId, $selectedMachineName): void {
                if ($selectedMachineId) {
                    $q->where('machine_id', $selectedMachineId)
                        ->orWhere('nama_mesin', $selectedMachineName);
                } else {
                    $q->where('nama_mesin', $selectedMachineName);
                }
            })
            ->first();

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $daysInfo = $this->buildDaysInfo($year, $month, $daysInMonth);

        $items = $this->resolveItems($record);

        return Inertia::render('operasi/input/patrol-check-mesin/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_id' => $unit->service_unit_id,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'machine_id' => $selectedMachineId,
                'nama_mesin' => $selectedMachineName,
            ],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'machines' => $machineOptions,
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'daysInMonth' => $daysInMonth,
            'daysInfo' => $daysInfo,
            'items' => $items,
            'shift_pagi' => $record?->shift_pagi ?? [],
            'shift_sore' => $record?->shift_sore ?? [],
            'shift_malam' => $record?->shift_malam ?? [],
            'catatan' => $record?->catatan ?? '',
            'can_write' => $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganPatrolCheckMesin),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganPatrolCheckMesin), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'machine_id' => ['nullable', 'integer'],
            'nama_mesin' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'items' => ['required', 'array'],
            'items.*.no' => ['required', 'integer'],
            'items.*.system' => ['required', 'string', 'max:255'],
            'items.*.peralatan' => ['required', 'string', 'max:255'],
            'items.*.checks' => ['nullable', 'array'],
            'shift_pagi' => ['nullable', 'array'],
            'shift_sore' => ['nullable', 'array'],
            'shift_malam' => ['nullable', 'array'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $namaMesin = trim($validated['nama_mesin']);
        $machineId = ! empty($validated['machine_id']) ? (int) $validated['machine_id'] : null;

        DB::transaction(function () use ($unit, $year, $month, $namaMesin, $machineId, $validated, $user): void {
            OperasiPatrolCheckMesin::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'nama_mesin' => $namaMesin,
                ],
                [
                    'machine_id' => $machineId,
                    'items' => $validated['items'],
                    'shift_pagi' => $validated['shift_pagi'] ?? [],
                    'shift_sore' => $validated['shift_sore'] ?? [],
                    'shift_malam' => $validated['shift_malam'] ?? [],
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );
        });

        $monthName = Indonesian::monthName($month);
        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Patrol Check Mesin {$namaMesin} - {$unit->name} {$monthName} {$year}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Patrol Check Mesin berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(($this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganPatrolCheckMesin) || $user->hasPermissionTo(PermissionName::OperasiLaporanView)), 403);

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);
        $machineId = $request->integer('machine_id') ?: null;
        $namaMesin = $request->string('nama_mesin')->trim()->toString();

        [$view, $data] = $this->pdfView($unit, $year, $month, $machineId, $namaMesin);

        $safeUnit = str_replace(' ', '_', $unit->name);
        $safeMesin = str_replace(' ', '_', $data['namaMesin']);
        $filename = "Patrol_Check_Mesin_{$safeUnit}_{$safeMesin}_{$month}_{$year}.pdf";

        return $this->streamReportPdf($request, $view, $data, $filename, 'landscape');
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $year, int $month, ?int $machineId = null, string $namaMesin = ''): array
    {
        if ($namaMesin === '') {
            $machine = $machineId ? Machine::query()->find($machineId) : null;
            $namaMesin = $machine?->name ?? 'Cummins #8';
        }

        $record = OperasiPatrolCheckMesin::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where(function ($q) use ($machineId, $namaMesin): void {
                if ($machineId) {
                    $q->where('machine_id', $machineId)
                        ->orWhere('nama_mesin', $namaMesin);
                } else {
                    $q->where('nama_mesin', $namaMesin);
                }
            })
            ->first();

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $daysInfo = $this->buildDaysInfo($year, $month, $daysInMonth);
        $items = $this->resolveItems($record);

        $monthName = strtoupper(Indonesian::monthName($month));
        $periodLabel = "{$monthName} {$year}";

        return ['operasi.input.patrol-check-mesin-pdf', [
            'unit' => $unit,
            'year' => $year,
            'month' => $month,
            'namaMesin' => $namaMesin,
            'periodLabel' => $periodLabel,
            'daysInMonth' => $daysInMonth,
            'daysInfo' => $daysInfo,
            'items' => $items,
            'shiftPagi' => $record?->shift_pagi ?? [],
            'shiftSore' => $record?->shift_sore ?? [],
            'shiftMalam' => $record?->shift_malam ?? [],
            'catatan' => $record?->catatan ?? '',
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * @return list<array{day: int, is_weekend: bool, day_name: string}>
     */
    private function buildDaysInfo(int $year, int $month, int $daysInMonth): array
    {
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dt = Carbon::createFromDate($year, $month, $d);
            $isWeekend = $dt->isWeekend();

            // Hari libur nasional khusus (misal 17 Agustus: HUT RI)
            if ($month === 8 && $d === 17) {
                $isWeekend = true;
            }

            $days[] = [
                'day' => $d,
                'is_weekend' => $isWeekend,
                'day_name' => substr($dt->locale('id')->isoFormat('ddd'), 0, 3),
            ];
        }

        return $days;
    }

    /**
     * @return list<array{no: int, system: string, peralatan: string, checks: array<string, string>}>
     */
    private function resolveItems(?OperasiPatrolCheckMesin $record): array
    {
        if ($record && ! empty($record->items)) {
            return $record->items;
        }

        return collect(OperasiPatrolCheckMesin::DEFAULT_ITEMS)->map(fn ($item): array => [
            'no' => $item['no'],
            'system' => $item['system'],
            'peralatan' => $item['peralatan'],
            'checks' => [],
        ])->all();
    }
}
