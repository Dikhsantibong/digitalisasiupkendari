<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\LogsheetStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LogsheetParameter;
use App\Models\LogsheetTemplate;
use App\Models\Machine;
use App\Models\OperatorLogsheet;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkScheduleEntry;
use App\Services\ActivityLogger;
use App\Services\Operasi\LogsheetAggregator;
use App\Services\Operator\LogsheetLayout;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operator logsheet — the field-data-entry layer of the standalone OPERATOR
 * module (no longer part of OPERASI). One sheet per machine per day: the
 * operator fills one time slot at a time through a simple "Create" modal, each
 * save landing on that hour for that machine. TL Operasi & Manager view
 * read-only. Parameters come from the {@see LogsheetParameter} master (never
 * hardcoded); time slots are values, not a fixed 24 rows.
 *
 * Parameters & slots follow the machine's {@see LogsheetTemplate} (jenis mesin,
 * user 2026-11-21): PLTD Poasia (Mirrlees) and Containerized (Cummins) differ.
 *
 * The OPERASI module may later aggregate these readings into its daily engine
 * reports via {@see LogsheetAggregator} — that hook is
 * prepared but intentionally not wired.
 */
class LogsheetController extends Controller
{
    /** @var list<string> */
    private const SHIFTS = ['A', 'B', 'C', 'D'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'logsheet_template_id']);
        $engine = $machines->firstWhere('id', (int) $request->integer('engine_id')) ?? $machines->first();

        $logDate = ($request->date('log_date') ?? Carbon::now())->toDateString();

        $template = LogsheetTemplate::forMachine($engine);
        $slots = $template?->slots() ?? LogsheetTemplate::DEFAULT_SLOTS;
        [$parameters, $logsheet, $rows] = $this->sheet($engine, $logDate);

        $isSubmitted = $logsheet?->status === LogsheetStatus::Submitted;

        return Inertia::render('operator/logsheet/index', [
            'filters' => ['unit_id' => $unit->id, 'engine_id' => $engine?->id, 'log_date' => $logDate],
            'stats' => $engine === null ? [] : $this->dayStats($parameters, $logsheet, count($slots)),
            'header' => [
                // A new sheet takes the operator's regu that day, so the phone needs no Shift picker.
                'shift' => $logsheet?->shift ?? $this->reguOf($user, $logDate),
                'status' => ($logsheet?->status ?? LogsheetStatus::Draft)->value,
            ],
            'parameters' => $parameters->map(fn (LogsheetParameter $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'sub_channel' => $p->sub_channel,
                'label' => $p->label(),
                'unit_of_measure' => $p->unit_of_measure,
                'group_label' => $p->group_label,
                'input_type' => $p->input_type ?: 'number',
                'options' => $p->options ?? [],
                'normal_value' => $p->normal_value,
                'alarm_value' => $p->alarm_value,
                'trip_value' => $p->trip_value,
            ])->all(),
            'template' => $template === null ? null : ['id' => $template->id, 'name' => $template->name, 'is_default' => $template->is_default],
            'rows' => $rows,
            'slots' => $slots,
            'shifts' => self::SHIFTS,
            'options' => [
                'units' => $units->all(),
                'machines' => $machines->map(fn (Machine $m): array => ['id' => $m->id, 'name' => $m->name])->all(),
            ],
            // The operator can always edit the sheet; TL/Manager never write.
            'can_write' => $user->hasPermissionTo(PermissionName::OperatorLogsheetWrite),
            'is_submitted' => $isSubmitted,
        ]);
    }

    /**
     * Save one time slot's readings for the machine/day (create or overwrite that
     * hour). Sending all-empty values clears the hour.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetWrite), 403);

        [$unit, $engine] = $this->resolveTarget($request);

        $parameters = $this->parametersOf($engine);
        $validated = $request->validate([
            'log_date' => ['required', 'date'],
            'shift' => ['nullable', Rule::in(self::SHIFTS)],
            'time_slot' => ['required', Rule::in($this->slotsOf($engine))],
            'values' => ['array'],
            'values.*' => ['nullable', 'max:60'],
        ]);
        $this->checkValues($validated['values'] ?? [], $parameters, 'values');

        $logDate = Carbon::parse($validated['log_date'])->toDateString();
        $logsheet = $this->findOrNewSheet($engine->id, $logDate);

        DB::transaction(function () use ($logsheet, $validated, $unit, $engine, $user, $parameters): void {
            $logsheet->fill(['unit_id' => $unit->id, 'engine_id' => $engine->id, 'input_by' => $user->id]);
            if (array_key_exists('shift', $validated)) {
                $logsheet->shift = $validated['shift'];
            }
            $logsheet->save();

            $logsheet->readings()->where('time_slot', $validated['time_slot'])->delete();
            $this->createReadings($logsheet, $validated['time_slot'], $validated['values'] ?? [], $parameters);
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan logsheet {$engine->name} {$logDate} jam {$validated['time_slot']}",
            $logsheet,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Data jam {$validated['time_slot']} disimpan."]);

        return back();
    }

    /**
     * The desktop sheet saved like a spreadsheet: every hour the operator
     * changed (`sheet` = time slot => parameter key => value) in one save.
     * Each sent hour replaces that hour's readings; other hours stay as they are.
     */
    public function storeSheet(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetWrite), 403);

        [$unit, $engine] = $this->resolveTarget($request);

        $templateSlots = $this->slotsOf($engine);
        $parameters = $this->parametersOf($engine);
        $validated = $request->validate([
            'log_date' => ['required', 'date'],
            'shift' => ['nullable', Rule::in(self::SHIFTS)],
            'sheet' => ['required', 'array', 'max:'.count($templateSlots)],
            'sheet.*' => ['array'],
            'sheet.*.*' => ['nullable', 'max:60'],
        ]);

        $slots = array_map('strval', array_keys($validated['sheet']));
        $unknown = array_diff($slots, $templateSlots);
        abort_if($unknown !== [], 422, 'Jam tidak dikenal.');
        foreach ($validated['sheet'] as $slot => $values) {
            $this->checkValues($values, $parameters, "sheet.{$slot}");
        }

        $logDate = Carbon::parse($validated['log_date'])->toDateString();
        $logsheet = $this->findOrNewSheet($engine->id, $logDate);

        DB::transaction(function () use ($logsheet, $validated, $unit, $engine, $user, $parameters, $slots): void {
            $logsheet->fill(['unit_id' => $unit->id, 'engine_id' => $engine->id, 'input_by' => $user->id]);
            if (array_key_exists('shift', $validated)) {
                $logsheet->shift = $validated['shift'];
            }
            $logsheet->save();

            $logsheet->readings()->whereIn('time_slot', $slots)->delete();
            foreach ($validated['sheet'] as $slot => $values) {
                $this->createReadings($logsheet, (string) $slot, $values, $parameters);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan logsheet {$engine->name} {$logDate} jam ".implode(', ', $slots),
            $logsheet,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logsheet tersimpan ('.count($slots).' jam).']);

        return back();
    }

    /**
     * PDF (A4 landscape) of one machine's logsheet for one day: every time slot
     * × every parameter, like the paper logsheet.
     */
    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetView), 403);

        [$unit, $engine] = $this->resolveTarget($request);
        $logDate = ($request->date('log_date') ?? Carbon::now())->toDateString();
        [$parameters, $logsheet, $rows] = $this->sheet($engine, $logDate);
        $date = Carbon::parse($logDate);
        $template = LogsheetTemplate::forMachine($engine);

        $pdf = Pdf::loadView('operator.logsheet-pdf', [
            'unit' => $unit,
            'engine' => $engine,
            'hariTanggal' => Indonesian::dayName($date).', '.Indonesian::longDate($date),
            'shift' => $logsheet?->shift,
            'status' => $logsheet?->status === LogsheetStatus::Submitted ? 'Terkirim' : 'Draft',
            'parameters' => $parameters,
            'headerRows' => LogsheetLayout::headerRows($parameters),
            'referenceRows' => LogsheetLayout::referenceRows($parameters),
            'templateName' => $template?->name,
            // The default (Cummins) sheet keeps its flat head; other jenis mesin print the grouped one.
            'groupedHead' => $template !== null && ! $template->is_default,
            'rows' => $rows,
            'stats' => $this->dayStats($parameters, $logsheet, count($template?->slots() ?? LogsheetTemplate::DEFAULT_SLOTS)),
            ...JadwalPdf::logos(),
        ])->setPaper('a4', 'landscape');

        $filename = sprintf('Logsheet_%s_%s_%s.pdf', str_replace(' ', '_', $unit->name), str_replace([' ', '#'], ['_', ''], $engine->name), $date->format('Ymd'));

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * The machine's sheet for the day: active parameters, the stored logsheet and
     * one row per time slot with its values keyed p_{parameter id}.
     *
     * @return array{0: Collection<int, LogsheetParameter>, 1: OperatorLogsheet|null, 2: list<array{time_slot: string, values: array<string, string|null>, filled: bool}>}
     */
    private function sheet(?Machine $engine, string $logDate): array
    {
        $parameters = $this->parametersOf($engine);
        $slots = $this->slotsOf($engine);

        $logsheet = $engine === null ? null : OperatorLogsheet::query()
            ->with('readings')
            ->where('engine_id', $engine->id)->whereDate('log_date', $logDate)->first();

        $stored = $logsheet?->readings->groupBy(fn ($r): string => substr((string) $r->time_slot, 0, 5)) ?? collect();

        $rows = collect($slots)->map(function (string $slot) use ($stored, $parameters): array {
            $byParam = ($stored->get($slot) ?? collect())->keyBy('parameter_id');
            $values = [];
            $filled = false;
            foreach ($parameters as $parameter) {
                $reading = $byParam->get($parameter->id);
                $value = $this->trimNumber($reading?->value) ?? $reading?->value_text;
                $values['p_'.$parameter->id] = $value;
                $filled = $filled || ($value !== null);
            }

            return ['time_slot' => $slot, 'values' => $values, 'filled' => $filled];
        })->all();

        return [$parameters, $logsheet, $rows];
    }

    /**
     * The active parameters of the machine's logsheet template, in column order.
     *
     * @return Collection<int, LogsheetParameter>
     */
    private function parametersOf(?Machine $engine): Collection
    {
        return LogsheetParameter::query()
            ->ofTemplate(LogsheetTemplate::forMachine($engine))
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return list<string>
     */
    private function slotsOf(?Machine $engine): array
    {
        return LogsheetTemplate::forMachine($engine)?->slots() ?? LogsheetTemplate::DEFAULT_SLOTS;
    }

    /**
     * Numbers for number parameters (not negative), a listed option for choice ones.
     *
     * @param  array<string, string|null>  $values
     * @param  Collection<int, LogsheetParameter>  $parameters
     */
    private function checkValues(array $values, Collection $parameters, string $prefix): void
    {
        $byId = $parameters->keyBy('id');
        $errors = [];
        foreach ($values as $key => $value) {
            $parameter = $byId->get((int) str_replace('p_', '', (string) $key));
            if ($parameter === null || $value === null || trim((string) $value) === '') {
                continue;
            }
            if ($parameter->isNumeric()) {
                if (! is_numeric($value)) {
                    $errors["{$prefix}.{$key}"] = 'Isian harus berupa angka.';
                } elseif ((float) $value < 0) {
                    $errors["{$prefix}.{$key}"] = 'Isian tidak boleh negatif.';
                }
            } elseif ($parameter->input_type === 'choice' && ! empty($parameter->options) && ! in_array(trim((string) $value), $parameter->options, true)) {
                $errors["{$prefix}.{$key}"] = 'Pilih salah satu: '.implode(', ', $parameter->options).'.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, string|null>  $values
     * @param  Collection<int, LogsheetParameter>  $parameters
     */
    private function createReadings(OperatorLogsheet $logsheet, string $slot, array $values, Collection $parameters): void
    {
        $byId = $parameters->keyBy('id');
        foreach ($values as $key => $value) {
            $parameter = $byId->get((int) str_replace('p_', '', (string) $key));
            if ($parameter === null || $value === null || trim((string) $value) === '') {
                continue;
            }
            $logsheet->readings()->create($parameter->isNumeric()
                ? ['time_slot' => $slot, 'parameter_id' => $parameter->id, 'value' => $value]
                : ['time_slot' => $slot, 'parameter_id' => $parameter->id, 'value_text' => trim((string) $value)]);
        }
    }

    /**
     * @return array{0: Unit, 1: Machine}
     */
    private function resolveTarget(Request $request): array
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($request->user()->canAccessUnit($unit), 403);

        $engine = Machine::query()->where('unit_id', $unit->id)->findOrFail($request->integer('engine_id'));

        return [$unit, $engine];
    }

    /**
     * Per-day accumulated figures derived from the sheet's readings, shown as
     * small cards above the table (auto-refresh when the date filter changes).
     *
     * @param  Collection<int, LogsheetParameter>  $parameters
     * @return list<array{label: string, value: string, unit?: string, hint?: string}>
     */
    private function dayStats($parameters, ?OperatorLogsheet $logsheet, int $totalSlots): array
    {
        $readings = $logsheet?->readings ?? collect();

        $loadId = $parameters->firstWhere('code', 'LOAD')?->id;
        $kwhId = $parameters->firstWhere('code', 'KWH_METER')?->id;

        $loadValues = $readings->where('parameter_id', $loadId)
            ->pluck('value')->filter(fn ($v): bool => $v !== null)->map(fn ($v): float => (float) $v);

        $kwhLast = $readings->where('parameter_id', $kwhId)
            ->sortByDesc(fn ($r): string => substr((string) $r->time_slot, 0, 5))->first()?->value;

        $filledSlots = $readings->groupBy(fn ($r): string => substr((string) $r->time_slot, 0, 5))->count();

        return [
            ['label' => 'Jam Terisi', 'value' => $filledSlots.' / '.$totalSlots, 'hint' => 'slot waktu'],
            ['label' => 'Beban Puncak', 'value' => $this->trimNumber($loadValues->max()) ?? '—', 'unit' => 'kW'],
            ['label' => 'Rata-rata Beban', 'value' => $loadValues->isNotEmpty() ? (string) $this->trimNumber(round((float) $loadValues->avg(), 1)) : '—', 'unit' => 'kW'],
            ['label' => 'kWh Meter Akhir', 'value' => $this->trimNumber($kwhLast) ?? '—', 'unit' => 'kWh'],
        ];
    }

    /**
     * Render a stored decimal without trailing zeros (0.7000 → "0.7", 10.0000 → "10").
     */
    private function trimNumber(int|float|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = (string) $value;

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    private function findOrNewSheet(int $engineId, string $logDate): OperatorLogsheet
    {
        return OperatorLogsheet::query()
            ->where('engine_id', $engineId)->whereDate('log_date', $logDate)->first()
            ?? new OperatorLogsheet(['engine_id' => $engineId, 'log_date' => $logDate]);
    }

    /**
     * The operator's regu (shift A–D) on a date: the Jadwal Shift entry's regu,
     * else the employee's own regu; null when it is not one of the shifts.
     */
    private function reguOf(User $user, string $date): ?string
    {
        $employee = Employee::query()->where('user_id', $user->id)->where('is_active', true)->first();
        if ($employee === null) {
            return null;
        }

        $regu = WorkScheduleEntry::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->value('regu') ?? $employee->regu;
        $regu = strtoupper(trim((string) $regu));

        return in_array($regu, self::SHIFTS, true) ? $regu : null;
    }
}
