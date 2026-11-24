<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LogsheetTemplate;
use App\Models\Machine;
use App\Models\OperasiPatrolCheckEntry;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\OperasiPatrolCheckTemplate;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkScheduleEntry;
use App\Services\ActivityLogger;
use App\Services\Operasi\OperasiReportTables;
use App\Services\Operator\PresenceRecorder;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

        $now = Carbon::now(PresenceRecorder::TIMEZONE);
        $year = (int) ($request->integer('year') ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'logsheet_template_id', 'patrol_check_template_id']);

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

        $selectedMachine = $machines->firstWhere('id', $selectedMachineId)
            ?? $machines->firstWhere('name', $selectedMachineName);
        if ($selectedMachine !== null) {
            $selectedMachineId = $selectedMachine->id;
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

        $template = $this->resolveTemplate($selectedMachine);
        $patrolFormat = $this->resolveFormat($record, $template);
        $patrolMeta = $template?->resolvedMeta() ?? OperasiPatrolCheckMesin::POASIA_META;
        $canWrite = $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganPatrolCheckMesin);

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $daysInfo = $this->buildDaysInfo($year, $month, $daysInMonth);
        $employee = $this->resolveUserEmployee($user, $unit->id);
        $userShifts = $this->userShiftMap($employee, $year, $month, $daysInMonth, $now);
        $userOperatorName = $employee?->name ?? $user->name;

        $entries = [];
        $activeEntry = null;
        if ($patrolFormat === OperasiPatrolCheckMesin::FORMAT_CHECKLIST) {
            $savedEntries = $record ? $record->entries()->get() : collect();
            $entries = $savedEntries->map(fn (OperasiPatrolCheckEntry $e): array => $this->serializeChecklistSummary($e))->all();

            $selectedEntry = null;
            if (! $request->boolean('new')) {
                $requestedEntryId = $request->integer('entry_id') ?: null;
                if ($requestedEntryId !== null) {
                    $selectedEntry = $savedEntries->firstWhere('id', $requestedEntryId);
                } elseif (! $canWrite && $savedEntries->isNotEmpty()) {
                    $selectedEntry = $savedEntries->last();
                }
            }

            $activeEntry = $this->buildChecklistEntryPayload(
                $selectedEntry,
                $template,
                $employee,
                $userOperatorName,
                $userShifts,
                $now,
                $year,
                $month,
                $request->string('tanggal')->trim()->toString(),
            );
            $items = $activeEntry['items'];
        } else {
            $items = $this->resolveItems($record, $selectedMachineId ? (int) $selectedMachineId : null, $unit->id);
        }

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
                'entry_id' => $activeEntry['id'] ?? null,
            ],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'machines' => $machineOptions,
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'patrol_format' => $patrolFormat,
            'patrol_meta' => $patrolMeta,
            'patrol_param_options' => OperasiPatrolCheckMesin::CHECKLIST_PARAMS,
            'shift_options' => OperasiPatrolCheckMesin::SHIFTS,
            'user_shifts' => $userShifts,
            'user_operator_name' => $userOperatorName,
            'entries' => $entries,
            'active_entry' => $activeEntry,
            'daysInMonth' => $daysInMonth,
            'daysInfo' => $daysInfo,
            'items' => $items,
            'shift_pagi' => $record?->shift_pagi ?? [],
            'shift_sore' => $record?->shift_sore ?? [],
            'shift_malam' => $record?->shift_malam ?? [],
            'catatan' => $activeEntry !== null ? $activeEntry['catatan'] : ($record?->catatan ?? ''),
            'can_write' => $canWrite,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganPatrolCheckMesin), 403);

        if ($request->input('patrol_format') === OperasiPatrolCheckMesin::FORMAT_CHECKLIST || $request->has('tanggal')) {
            return $this->storeChecklist($request, $user);
        }

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
            'items.*.findings' => ['nullable', 'array'],
            'items.*.findings.*.catatan' => ['nullable', 'string', 'max:500'],
            'items.*.findings.*.eviden' => ['nullable', 'string', 'max:255'],
            'items.*.findings.*.eviden_file' => ['nullable', 'image', 'max:10240'],
            'shift_pagi' => ['nullable', 'array'],
            'shift_sore' => ['nullable', 'array'],
            'shift_malam' => ['nullable', 'array'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'items.*.findings.*.eviden_file.image' => 'Eviden harus berupa foto.',
            'items.*.findings.*.eviden_file.max' => 'Ukuran foto eviden paling besar 10 MB.',
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $namaMesin = trim($validated['nama_mesin']);
        $machineId = ! empty($validated['machine_id']) ? (int) $validated['machine_id'] : null;

        $existingRecord = OperasiPatrolCheckMesin::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('nama_mesin', $namaMesin)
            ->first();

        $removedEviden = [];
        $normalizedMonthlyItems = array_values(array_map(function (array $item, int $idx) use ($request, $unit, $existingRecord, &$removedEviden): array {
            $checks = is_array($item['checks'] ?? null) ? $item['checks'] : [];
            $rawFindings = is_array($item['findings'] ?? null) ? $item['findings'] : [];
            $prevFindings = is_array($existingRecord?->items[$idx]['findings'] ?? null)
                ? $existingRecord->items[$idx]['findings']
                : [];

            $findings = [];
            foreach ($checks as $day => $status) {
                $dayKey = (string) $day;
                if ($status !== 'T') {
                    if (! empty($prevFindings[$dayKey]['eviden'])) {
                        $removedEviden[] = $prevFindings[$dayKey]['eviden'];
                    }

                    continue;
                }

                $dayInput = is_array($rawFindings[$dayKey] ?? null) ? $rawFindings[$dayKey] : [];
                $prevEviden = ($prevFindings[$dayKey]['eviden'] ?? null) ?: null;
                $upload = $request->file("items.{$idx}.findings.{$dayKey}.eviden_file");
                $eviden = $this->evidenAfterSave(
                    $dayInput,
                    $upload instanceof UploadedFile ? $upload : null,
                    $prevEviden,
                    $unit->id,
                );
                if ($prevEviden !== null && $prevEviden !== $eviden) {
                    $removedEviden[] = $prevEviden;
                }

                $catatan = trim((string) ($dayInput['catatan'] ?? ($prevFindings[$dayKey]['catatan'] ?? '')));
                if ($catatan !== '' || $eviden !== null) {
                    $findings[$dayKey] = [
                        'catatan' => $catatan,
                        'eviden' => $eviden,
                    ];
                }
            }

            foreach ($prevFindings as $prevDay => $prevData) {
                if (($checks[(string) $prevDay] ?? '') !== 'T' && ! empty($prevData['eviden'])) {
                    $removedEviden[] = $prevData['eviden'];
                }
            }

            $row = [
                'no' => (int) $item['no'],
                'system' => (string) $item['system'],
                'peralatan' => (string) $item['peralatan'],
                'checks' => $checks,
            ];
            if ($findings !== []) {
                $row['findings'] = $findings;
            }

            return $row;
        }, $validated['items'], array_keys($validated['items'])));

        DB::transaction(function () use ($unit, $year, $month, $namaMesin, $machineId, $validated, $normalizedMonthlyItems, $user): void {
            OperasiPatrolCheckMesin::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'nama_mesin' => $namaMesin,
                ],
                [
                    'machine_id' => $machineId,
                    'format' => OperasiPatrolCheckMesin::FORMAT_MONTHLY,
                    'items' => $normalizedMonthlyItems,
                    'shift_pagi' => $validated['shift_pagi'] ?? [],
                    'shift_sore' => $validated['shift_sore'] ?? [],
                    'shift_malam' => $validated['shift_malam'] ?? [],
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );
        });

        $this->deleteEviden($removedEviden);
        $this->syncLaporanProjectSection($unit, $year, $month);

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

    private function storeChecklist(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'machine_id' => ['nullable', 'integer'],
            'nama_mesin' => ['required', 'string', 'max:255'],
            'entry_id' => ['nullable', 'integer'],
            'tanggal' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:40'],
            'jam' => ['required', 'string', 'max:10', 'regex:/^\d{1,2}[:.]\d{2}$/'],
            'operator_name' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.no' => ['required', 'integer'],
            'items.*.system' => ['required', 'string', 'max:255'],
            'items.*.peralatan' => ['required', 'string', 'max:255'],
            'items.*.applicable' => ['required', 'array'],
            'items.*.checked' => ['nullable', 'array'],
            'items.*.status' => ['nullable', 'string', Rule::in(['', 'N', 'TN'])],
            'items.*.catatan' => ['nullable', 'string', 'max:500'],
            'items.*.eviden' => ['nullable', 'string', 'max:255'],
            'items.*.eviden_file' => ['nullable', 'image', 'max:10240'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'tanggal.required' => 'Tanggal pemeriksaan wajib diisi.',
            'shift.required' => 'Shift wajib dipilih.',
            'jam.required' => 'Jam pemeriksaan wajib diisi.',
            'jam.regex' => 'Jam pemeriksaan harus berformat HH:MM (mis. 08:30).',
            'operator_name.required' => 'Nama operator wajib diisi.',
            'items.*.eviden_file.image' => 'Eviden harus berupa foto.',
            'items.*.eviden_file.max' => 'Ukuran foto eviden paling besar 10 MB.',
        ]);

        $validator->after(function ($v) use ($request): void {
            foreach ((array) $request->input('items', []) as $idx => $item) {
                if (($item['status'] ?? '') === 'TN' && trim((string) ($item['catatan'] ?? '')) === '') {
                    $name = $item['peralatan'] ?? ('komponen #'.($idx + 1));
                    $v->errors()->add("items.{$idx}.catatan", "Kolom TN wajib diberi uraian alasan / tindakan awal ({$name}).");
                }
            }
        });

        $validated = $validator->validate();

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $tanggal = Carbon::parse($validated['tanggal']);
        $year = $tanggal->year;
        $month = $tanggal->month;
        $namaMesin = trim($validated['nama_mesin']);
        $machineId = ! empty($validated['machine_id']) ? (int) $validated['machine_id'] : null;
        $jamParts = explode(':', str_replace('.', ':', trim($validated['jam'])));
        $jam = sprintf('%02d:%02d', (int) $jamParts[0], (int) ($jamParts[1] ?? 0));
        $paramKeys = array_column(OperasiPatrolCheckMesin::CHECKLIST_PARAMS, 'key');

        $existingEntry = null;
        $oldParent = null;
        if (! empty($validated['entry_id'])) {
            $existingEntry = OperasiPatrolCheckEntry::query()
                ->with('patrolCheckMesin')
                ->findOrFail((int) $validated['entry_id']);
            abort_unless($existingEntry->patrolCheckMesin?->unit_id === $unit->id, 403);
            $oldParent = $existingEntry->patrolCheckMesin;
        }

        $removedEviden = [];
        $normalizedItems = array_values(array_map(function (array $item, int $idx) use ($request, $unit, $paramKeys, $existingEntry, &$removedEviden): array {
            $applicable = [];
            $checked = [];
            foreach ($paramKeys as $key) {
                $isApp = filter_var($item['applicable'][$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                $applicable[$key] = $isApp;
                $checked[$key] = $isApp && filter_var($item['checked'][$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            $status = (string) ($item['status'] ?? '');
            $prevEviden = ($existingEntry?->items[$idx]['eviden'] ?? null) ?: null;

            // Normal (N) or unselected rows carry NO follow-up note or eviden photo.
            if ($status === 'TN') {
                $catatan = trim((string) ($item['catatan'] ?? ''));
                $upload = $request->file("items.{$idx}.eviden_file");
                $eviden = $this->evidenAfterSave(
                    $item,
                    $upload instanceof UploadedFile ? $upload : null,
                    $prevEviden,
                    $unit->id,
                );
                if ($prevEviden !== null && $prevEviden !== $eviden) {
                    $removedEviden[] = $prevEviden;
                }
            } else {
                $catatan = '';
                $eviden = null;
                if ($prevEviden !== null) {
                    $removedEviden[] = $prevEviden;
                }
            }

            return [
                'no' => $idx + 1,
                'system' => trim((string) $item['system']),
                'peralatan' => trim((string) $item['peralatan']),
                'applicable' => $applicable,
                'checked' => $checked,
                'status' => $status,
                'catatan' => $catatan,
                'eviden' => $eviden,
            ];
        }, $validated['items'], array_keys($validated['items'])));

        $now = Carbon::now(PresenceRecorder::TIMEZONE);
        $employee = $this->resolveUserEmployee($user, $unit->id);
        $resolvedShift = $employee !== null
            ? $this->userShiftForDate($employee, $tanggal->toDateString(), $now)
            : (trim((string) ($validated['shift'] ?? '')) ?: $this->userShiftForDate(null, $tanggal->toDateString(), $now));
        $resolvedOperatorName = $employee !== null
            ? $employee->name
            : (trim((string) ($validated['operator_name'] ?? '')) ?: $user->name);

        $savedEntry = DB::transaction(function () use ($unit, $year, $month, $namaMesin, $machineId, $validated, $tanggal, $jam, $resolvedShift, $resolvedOperatorName, $normalizedItems, $user, $existingEntry, $oldParent): OperasiPatrolCheckEntry {
            $parent = OperasiPatrolCheckMesin::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'nama_mesin' => $namaMesin,
                ],
                [
                    'machine_id' => $machineId,
                    'format' => OperasiPatrolCheckMesin::FORMAT_CHECKLIST,
                    'items' => null,
                    'shift_pagi' => null,
                    'shift_sore' => null,
                    'shift_malam' => null,
                    'catatan' => null,
                    'input_by' => $user->id,
                ],
            );

            $payload = [
                'patrol_check_mesin_id' => $parent->id,
                'tanggal' => $tanggal->toDateString(),
                'shift' => $resolvedShift,
                'jam' => $jam,
                'operator_name' => $resolvedOperatorName,
                'items' => $normalizedItems,
                'catatan' => ($validated['catatan'] ?? '') !== '' ? trim((string) $validated['catatan']) : null,
                'input_by' => $user->id,
            ];

            if ($existingEntry !== null) {
                $existingEntry->update($payload);
                $entry = $existingEntry->fresh();
                if ($oldParent !== null && $oldParent->id !== $parent->id && $oldParent->entries()->doesntExist()) {
                    $oldParent->delete();
                }

                return $entry;
            }

            return OperasiPatrolCheckEntry::query()->create($payload);
        });

        $this->deleteEviden($removedEviden);
        $this->syncLaporanProjectSection($unit, $year, $month);
        if ($oldParent !== null && ($oldParent->year !== $year || $oldParent->month !== $month)) {
            $this->syncLaporanProjectSection($unit, $oldParent->year, $oldParent->month);
        }

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Patrol Check Mesin {$namaMesin} ({$tanggal->format('d/m/Y')} Shift {$resolvedShift} {$jam}) - {$unit->name}",
            $savedEntry,
            unit: $unit->id,
        );

        return to_route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'machine_id' => $machineId,
            'nama_mesin' => $namaMesin,
            'entry_id' => $savedEntry->id,
        ])->with('toast', [
            'type' => 'success',
            'message' => "Patrol Check ({$tanggal->format('d/m/Y')} · {$resolvedShift} · {$jam}) berhasil disimpan.",
        ]);
    }

    public function destroyEntry(Request $request, OperasiPatrolCheckEntry $entry): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganPatrolCheckMesin), 403);

        $parent = $entry->patrolCheckMesin()->with('unit')->firstOrFail();
        abort_unless($user->canAccessUnit($parent->unit), 403);

        $query = [
            'unit_id' => $parent->unit_id,
            'year' => $parent->year,
            'month' => $parent->month,
            'machine_id' => $parent->machine_id,
            'nama_mesin' => $parent->nama_mesin,
        ];

        $removedEviden = collect($entry->items ?? [])
            ->pluck('eviden')
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($entry, $parent): void {
            $entry->delete();
            if ($parent->format === OperasiPatrolCheckMesin::FORMAT_CHECKLIST && $parent->entries()->doesntExist()) {
                $parent->delete();
            }
        });

        $this->deleteEviden($removedEviden);
        $this->syncLaporanProjectSection($parent->unit, $parent->year, $parent->month);

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus entri Patrol Check {$parent->nama_mesin} - {$parent->unit->name}",
            $parent->unit,
            unit: $parent->unit_id,
        );

        return to_route('operasi.input.patrol-check-mesin.index', $query)->with('toast', [
            'type' => 'success',
            'message' => 'Pemeriksaan Patrol Check berhasil dihapus.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(($this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganPatrolCheckMesin) || $user->hasPermissionTo(PermissionName::OperasiLaporanView)), 403);

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now(PresenceRecorder::TIMEZONE);
        $year = (int) ($request->integer('year') ?: $now->year);
        $month = (int) ($request->integer('month') ?: $now->month);
        $machineId = $request->integer('machine_id') ?: null;
        $namaMesin = $request->string('nama_mesin')->trim()->toString();
        $entryId = $request->integer('entry_id') ?: null;

        [$view, $data] = $this->pdfView($unit, $year, $month, $machineId, $namaMesin, $entryId);

        $safeUnit = str_replace(' ', '_', $unit->name);
        $safeMesin = str_replace(' ', '_', $data['namaMesin']);
        $filename = "Patrol_Check_Mesin_{$safeUnit}_{$safeMesin}_{$month}_{$year}.pdf";

        return $this->streamReportPdf($request, $view, $data, $filename, 'landscape');
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $year, int $month, ?int $machineId = null, string $namaMesin = '', ?int $entryId = null): array
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

        $resolvedMachineId = $machineId ?? $record?->machine_id;
        $machine = $resolvedMachineId
            ? Machine::query()->where('unit_id', $unit->id)->find($resolvedMachineId)
            : Machine::query()->where('unit_id', $unit->id)->where('name', $namaMesin)->first();
        $template = $this->resolveTemplate($machine);
        $patrolFormat = $this->resolveFormat($record, $template);
        $monthName = strtoupper(Indonesian::monthName($month));
        $periodLabel = "{$monthName} {$year}";

        if ($patrolFormat === OperasiPatrolCheckMesin::FORMAT_CHECKLIST) {
            $entries = $record
                ? $record->entries()
                    ->when($entryId !== null, fn ($q) => $q->where('id', $entryId))
                    ->get()
                : collect();

            $renderedEntries = $entries->map(fn (OperasiPatrolCheckEntry $e): array => [
                'tanggal' => $e->tanggal->format('d/m/Y'),
                'shift' => $e->shift,
                'jam' => $e->jam,
                'operator_name' => $e->operator_name,
                'items' => $this->normalizeChecklistItems($e->items ?? [], $template, embedEviden: true),
                'catatan' => $e->catatan ?? '',
            ])->all();

            if ($renderedEntries === []) {
                $renderedEntries = [[
                    'tanggal' => '-',
                    'shift' => '-',
                    'jam' => '-',
                    'operator_name' => '-',
                    'items' => $this->resolveChecklistItems(null, $template),
                    'catatan' => '',
                ]];
            }

            return ['operasi.input.patrol-check-mesin-pdf', [
                'unit' => $unit,
                'year' => $year,
                'month' => $month,
                'namaMesin' => $namaMesin,
                'periodLabel' => $periodLabel,
                'patrolFormat' => OperasiPatrolCheckMesin::FORMAT_CHECKLIST,
                'patrolMeta' => $template?->resolvedMeta() ?? OperasiPatrolCheckMesin::POASIA_META,
                'checklistParams' => OperasiPatrolCheckMesin::CHECKLIST_PARAMS,
                'entries' => $renderedEntries,
                'daysInMonth' => 0,
                'daysInfo' => [],
                'items' => [],
                'shiftPagi' => [],
                'shiftSore' => [],
                'shiftMalam' => [],
                'catatan' => '',
                ...JadwalPdf::logos(),
            ]];
        }

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $daysInfo = $this->buildDaysInfo($year, $month, $daysInMonth);
        $items = $this->resolveItems($record, $machine?->id ?? ($machineId ? (int) $machineId : null), $unit->id, embedEviden: true);

        return ['operasi.input.patrol-check-mesin-pdf', [
            'unit' => $unit,
            'year' => $year,
            'month' => $month,
            'namaMesin' => $namaMesin,
            'periodLabel' => $periodLabel,
            'patrolFormat' => OperasiPatrolCheckMesin::FORMAT_MONTHLY,
            'patrolMeta' => $template?->resolvedMeta() ?? OperasiPatrolCheckMesin::POASIA_META,
            'checklistParams' => OperasiPatrolCheckMesin::CHECKLIST_PARAMS,
            'entries' => [],
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
     * Resolve the Patrol Check template for a machine: its assigned Template
     * Patrol Check, else the default Template Patrol Check. When a machine still
     * carries legacy items inside its LogsheetTemplate without an explicit Patrol
     * Check template, return null so resolveItems falls back seamlessly.
     */
    private function resolveTemplate(?Machine $machine): ?OperasiPatrolCheckTemplate
    {
        if ($machine?->patrol_check_template_id !== null) {
            $assigned = OperasiPatrolCheckTemplate::query()->find($machine->patrol_check_template_id);
            if ($assigned !== null) {
                return $assigned;
            }
        }

        if ($machine !== null && ! empty(LogsheetTemplate::forMachine($machine)?->patrol_items)) {
            return null;
        }

        return OperasiPatrolCheckTemplate::default();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveItems(?OperasiPatrolCheckMesin $record, ?int $machineId = null, ?int $unitId = null, bool $embedEviden = false): array
    {
        if ($record && ! empty($record->items)) {
            return array_values(array_map(function (array $item) use ($embedEviden): array {
                $rawFindings = is_array($item['findings'] ?? null) ? $item['findings'] : [];
                $findings = [];
                foreach ($rawFindings as $day => $f) {
                    if (! is_array($f)) {
                        continue;
                    }
                    $eviden = ($f['eviden'] ?? null) ?: null;
                    $findings[(string) $day] = [
                        'catatan' => (string) ($f['catatan'] ?? ''),
                        'eviden' => $eviden,
                        'eviden_url' => $this->evidenUrl($eviden, $embedEviden),
                    ];
                }
                if ($findings !== []) {
                    $item['findings'] = $findings;
                }

                return $item;
            }, $record->items));
        }

        $machine = $machineId === null ? null : Machine::query()->where('unit_id', $unitId)->find($machineId);
        $template = $this->resolveTemplate($machine);

        if ($template !== null) {
            return collect($template->resolvedItems())->values()->map(fn (array $item, int $i): array => [
                'no' => $i + 1,
                'system' => (string) ($item['system'] ?? ''),
                'peralatan' => (string) ($item['peralatan'] ?? ''),
                'checks' => [],
            ])->all();
        }

        // Legacy compatibility: LogsheetTemplate::patrol_items when no PatrolCheckTemplate is linked.
        $legacyItems = $machine === null ? [] : (LogsheetTemplate::forMachine($machine)?->patrol_items ?? []);
        if ($legacyItems !== []) {
            return collect($legacyItems)->values()->map(fn (array $item, int $i): array => [
                'no' => $i + 1,
                'system' => (string) $item['system'],
                'peralatan' => (string) $item['peralatan'],
                'checks' => [],
            ])->all();
        }

        return collect(OperasiPatrolCheckMesin::DEFAULT_ITEMS)->map(fn ($item): array => [
            'no' => $item['no'],
            'system' => $item['system'],
            'peralatan' => $item['peralatan'],
            'checks' => [],
        ])->all();
    }

    private function resolveFormat(?OperasiPatrolCheckMesin $record, ?OperasiPatrolCheckTemplate $template): string
    {
        if ($template?->isChecklist()) {
            return OperasiPatrolCheckMesin::FORMAT_CHECKLIST;
        }

        if ($record !== null && ($record->format === OperasiPatrolCheckMesin::FORMAT_CHECKLIST || $record->entries()->exists())) {
            return OperasiPatrolCheckMesin::FORMAT_CHECKLIST;
        }

        return $template?->resolvedFormat() ?? OperasiPatrolCheckMesin::FORMAT_MONTHLY;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeChecklistSummary(OperasiPatrolCheckEntry $entry): array
    {
        $items = (array) ($entry->items ?? []);
        $normalCount = 0;
        $tnCount = 0;
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            if ($status === 'N') {
                $normalCount++;
            } elseif ($status === 'TN') {
                $tnCount++;
            }
        }

        return [
            'id' => $entry->id,
            'tanggal' => $entry->tanggal->toDateString(),
            'tanggal_label' => $entry->tanggal->format('d/m/Y'),
            'shift' => $entry->shift,
            'jam' => $entry->jam,
            'operator_name' => $entry->operator_name,
            'normal_count' => $normalCount,
            'tn_count' => $tnCount,
            'total_items' => count($items),
            'has_catatan' => trim((string) ($entry->catatan ?? '')) !== '',
        ];
    }

    /**
     * @param  array<string, string>  $userShifts
     * @return array<string, mixed>
     */
    private function buildChecklistEntryPayload(
        ?OperasiPatrolCheckEntry $entry,
        ?OperasiPatrolCheckTemplate $template,
        ?Employee $employee,
        string $operatorDefault,
        array $userShifts,
        Carbon $now,
        int $year,
        int $month,
        string $requestedDate,
    ): array {
        if ($entry !== null) {
            return [
                'id' => $entry->id,
                'tanggal' => $entry->tanggal->toDateString(),
                'shift' => $entry->shift,
                'jam' => $entry->jam,
                'operator_name' => $entry->operator_name,
                'items' => $this->normalizeChecklistItems($entry->items ?? [], $template, embedEviden: true),
                'catatan' => $entry->catatan ?? '',
            ];
        }

        $defaultDate = ($year === $now->year && $month === $now->month)
            ? $now->toDateString()
            : Carbon::createFromDate($year, $month, 1)->toDateString();
        if ($requestedDate !== '') {
            try {
                $defaultDate = Carbon::parse($requestedDate)->toDateString();
            } catch (\Throwable) {
            }
        }

        $defaultShift = $userShifts[$defaultDate] ?? $this->userShiftForDate($employee, $defaultDate, $now);

        return [
            'id' => null,
            'tanggal' => $defaultDate,
            'shift' => $defaultShift,
            'jam' => $now->format('H:i'),
            'operator_name' => $operatorDefault,
            'items' => $this->resolveChecklistItems(null, $template),
            'catatan' => '',
        ];
    }

    private function resolveUserEmployee(User $user, int $unitId): ?Employee
    {
        return Employee::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN unit_id = ? THEN 0 ELSE 1 END', [$unitId])
            ->first();
    }

    /**
     * Resolve the logged-in user's shift on a given date from their Jadwal Shift
     * (WorkScheduleEntry) and Employee regu, falling back to the current time slot.
     */
    private function userShiftForDate(?Employee $employee, string $date, Carbon $now): string
    {
        if ($employee !== null) {
            $scheduleEntry = WorkScheduleEntry::query()
                ->with('attendanceCode')
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->first();

            $regu = strtoupper(trim((string) ($scheduleEntry?->regu ?? $employee->regu ?? '')));
            $code = strtoupper(trim((string) ($scheduleEntry?->attendanceCode?->code ?? '')));
            $label = trim((string) ($scheduleEntry?->attendanceCode?->label ?? ''));

            $timeShift = match (true) {
                $code === 'P' || strcasecmp($label, 'Pagi') === 0 => 'Pagi',
                $code === 'S' || strcasecmp($label, 'Sore') === 0 => 'Sore',
                $code === 'M' || strcasecmp($label, 'Malam') === 0 => 'Malam',
                default => null,
            };

            if ($regu !== '' && $timeShift !== null) {
                return "Shift {$regu} ({$timeShift})";
            }
            if ($regu !== '') {
                return "Shift {$regu}";
            }
            if ($timeShift !== null) {
                return $timeShift;
            }
        }

        $hour = $now->hour;

        return ($hour >= 8 && $hour < 16) ? 'Pagi' : ($hour >= 16 ? 'Sore' : 'Malam');
    }

    /**
     * @return array<string, string>
     */
    private function userShiftMap(?Employee $employee, int $year, int $month, int $daysInMonth, Carbon $now): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $entriesByDate = collect();
        if ($employee !== null) {
            $entriesByDate = WorkScheduleEntry::query()
                ->with('attendanceCode')
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', $from)
                ->whereDate('work_date', '<=', $to)
                ->get()
                ->keyBy(fn (WorkScheduleEntry $e): string => $e->work_date->toDateString());
        }

        $hour = $now->hour;
        $fallbackTimeShift = ($hour >= 8 && $hour < 16) ? 'Pagi' : ($hour >= 16 ? 'Sore' : 'Malam');
        $empRegu = strtoupper(trim((string) ($employee?->regu ?? '')));

        $map = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            /** @var WorkScheduleEntry|null $scheduleEntry */
            $scheduleEntry = $entriesByDate->get($date);
            $regu = strtoupper(trim((string) ($scheduleEntry?->regu ?? $empRegu)));
            $code = strtoupper(trim((string) ($scheduleEntry?->attendanceCode?->code ?? '')));
            $label = trim((string) ($scheduleEntry?->attendanceCode?->label ?? ''));

            $timeShift = match (true) {
                $code === 'P' || strcasecmp($label, 'Pagi') === 0 => 'Pagi',
                $code === 'S' || strcasecmp($label, 'Sore') === 0 => 'Sore',
                $code === 'M' || strcasecmp($label, 'Malam') === 0 => 'Malam',
                default => null,
            };

            if ($regu !== '' && $timeShift !== null) {
                $map[$date] = "Shift {$regu} ({$timeShift})";
            } elseif ($regu !== '') {
                $map[$date] = "Shift {$regu}";
            } elseif ($timeShift !== null) {
                $map[$date] = $timeShift;
            } else {
                $map[$date] = $fallbackTimeShift;
            }
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>|null  $savedItems
     * @return list<array<string, mixed>>
     */
    private function resolveChecklistItems(?array $savedItems, ?OperasiPatrolCheckTemplate $template): array
    {
        if ($savedItems !== null && $savedItems !== []) {
            return $this->normalizeChecklistItems($savedItems, $template);
        }

        $source = ($template?->items ?: null) ?? OperasiPatrolCheckMesin::POASIA_ITEMS;
        $paramKeys = array_column(OperasiPatrolCheckMesin::CHECKLIST_PARAMS, 'key');

        return collect($source)->values()->map(function (array $item, int $i) use ($paramKeys): array {
            $rawParams = is_array($item['params'] ?? null) ? $item['params'] : null;
            $applicable = [];
            $checked = [];
            foreach ($paramKeys as $key) {
                $applicable[$key] = $rawParams === null ? true : (bool) ($rawParams[$key] ?? false);
                $checked[$key] = false;
            }

            return [
                'no' => $i + 1,
                'system' => (string) ($item['system'] ?? 'MESIN'),
                'peralatan' => (string) ($item['peralatan'] ?? ''),
                'applicable' => $applicable,
                'checked' => $checked,
                'status' => '',
                'catatan' => '',
                'eviden' => null,
                'eviden_url' => null,
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeChecklistItems(array $items, ?OperasiPatrolCheckTemplate $template, bool $embedEviden = false): array
    {
        $paramKeys = array_column(OperasiPatrolCheckMesin::CHECKLIST_PARAMS, 'key');
        if ($items === []) {
            return $this->resolveChecklistItems(null, $template);
        }

        return collect($items)->values()->map(function (array $item, int $i) use ($paramKeys, $embedEviden): array {
            $rawApplicable = is_array($item['applicable'] ?? null) ? $item['applicable'] : (is_array($item['params'] ?? null) ? $item['params'] : null);
            $rawChecked = is_array($item['checked'] ?? null) ? $item['checked'] : [];
            $applicable = [];
            $checked = [];
            foreach ($paramKeys as $key) {
                $isApp = $rawApplicable === null ? true : filter_var($rawApplicable[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                $applicable[$key] = $isApp;
                $checked[$key] = $isApp && filter_var($rawChecked[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            $status = (string) ($item['status'] ?? '');
            $eviden = $status === 'TN' ? (($item['eviden'] ?? null) ?: null) : null;

            return [
                'no' => $i + 1,
                'system' => (string) ($item['system'] ?? 'MESIN'),
                'peralatan' => (string) ($item['peralatan'] ?? ''),
                'applicable' => $applicable,
                'checked' => $checked,
                'status' => $status,
                'catatan' => $status === 'TN' ? (string) ($item['catatan'] ?? '') : '',
                'eviden' => $eviden,
                'eviden_url' => $this->evidenUrl($eviden, $embedEviden),
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function evidenAfterSave(array $input, ?UploadedFile $upload, ?string $current, int $unitId): ?string
    {
        if ($upload instanceof UploadedFile) {
            return $upload->store("operasi-patrol-check/{$unitId}", 'public');
        }

        if (! array_key_exists('eviden', $input)) {
            return $current;
        }

        return $current !== null && $input['eviden'] === $current ? $current : null;
    }

    /**
     * @param  list<string|null>  $paths
     */
    private function deleteEviden(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths)));
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function evidenUrl(?string $path, bool $embed = true): ?string
    {
        $disk = Storage::disk('public');
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * Automatically keep section 12 (Pembuatan Patrol Check) inside any saved
     * Laporan Project Operasi document for the same unit & month in sync with
     * the latest Patrol Check data, uraian alasan, and eviden photos.
     */
    public function syncLaporanProjectSection(Unit $unit, int $year, int $month): void
    {
        $documents = OperasiReportDocument::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get();

        if ($documents->isEmpty()) {
            return;
        }

        $sectionHtml = self::renderLaporanProjectSectionHtml($unit, $year, $month);
        foreach ($documents as $doc) {
            if (! is_string($doc->content_html) || $doc->content_html === '') {
                continue;
            }
            $updated = self::replaceSection412($doc->content_html, $sectionHtml);
            if ($updated !== $doc->content_html) {
                $doc->update(['content_html' => $updated]);
            }
        }
    }

    public static function renderLaporanProjectSectionHtml(Unit $unit, int $year, int $month): string
    {
        $built = app(OperasiReportTables::class)->build($unit, $month, $year, 0);
        $part = $built['tables']['patrol_check'] ?? ['scope' => '', 'css' => '', 'body' => ''];
        $periodLabel = Indonesian::monthName($month).' '.$year;
        $kop = ['unitName' => $unit->name];

        if (trim((string) ($part['body'] ?? '')) !== '') {
            $inner = view('operasi.laporan.partials.table-fragment', ['part' => $part])->render();
        } else {
            $inner = view('operasi.laporan.partials.kop', $kop + ['title' => 'PEMBUATAN PATROL CHECK'])->render()
                .'<div class="op-sub-title">12. Pembuatan Patrol Check — '.e($periodLabel).'</div>'
                .view('operasi.laporan.partials.no-data', ['message' => 'Belum ada Patrol Check Mesin yang diisi pada periode ini.'])->render();
        }

        return '<div class="op-section op-landscape" id="sec-4-12">'.$inner.'</div>';
    }

    public static function replaceSection412(string $html, string $replacementSection): string
    {
        $pattern = '#<div\s+class="op-section\s+op-landscape"\s+id="sec-4-12">.*?(?=<div\s+class="page-break">\s*</div>\s*(?:{{--\s*13\.|<div\s+class="op-section[^>]*id="sec-4-13"))#si';
        if (preg_match($pattern, $html)) {
            return (string) preg_replace($pattern, $replacementSection."\n\n", $html, 1);
        }

        return $html;
    }
}
