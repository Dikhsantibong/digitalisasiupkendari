<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\LogsheetStatus;
use App\Enums\PermissionName;
use App\Enums\PlantType;
use App\Http\Controllers\Controller;
use App\Models\LogsheetParameter;
use App\Models\Machine;
use App\Models\OperatorLogsheet;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\LogsheetAggregator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
 * The OPERASI module may later aggregate these readings into its daily engine
 * reports via {@see LogsheetAggregator} — that hook is
 * prepared but intentionally not wired.
 */
class LogsheetController extends Controller
{
    /**
     * The available time slots: every hour 01:00–24:00 plus the evening peak
     * half-hours. Matches the Cummins logsheet.
     *
     * @var list<string>
     */
    private const SLOTS = [
        '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00',
        '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00',
        '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30',
        '21:00', '21:30', '22:00', '23:00', '24:00',
    ];

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

        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $engine = $machines->firstWhere('id', (int) $request->integer('engine_id')) ?? $machines->first();

        $logDate = ($request->date('log_date') ?? Carbon::now())->toDateString();

        // For now every unit uses the shared parameter set. When machines/units
        // carry a plant-type marker, resolve the set from that instead.
        $parameters = LogsheetParameter::query()
            ->where('plant_type', PlantType::All->value)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        $logsheet = $engine === null ? null : OperatorLogsheet::query()
            ->with('readings')
            ->where('engine_id', $engine->id)->whereDate('log_date', $logDate)->first();

        $stored = $logsheet?->readings->groupBy(fn ($r): string => substr((string) $r->time_slot, 0, 5)) ?? collect();

        $rows = collect(self::SLOTS)->map(function (string $slot) use ($stored, $parameters): array {
            $byParam = ($stored->get($slot) ?? collect())->keyBy('parameter_id');
            $values = [];
            $filled = false;
            foreach ($parameters as $parameter) {
                $value = $byParam->get($parameter->id)?->value;
                $values['p_'.$parameter->id] = $this->trimNumber($value);
                $filled = $filled || ($value !== null);
            }

            return ['time_slot' => $slot, 'values' => $values, 'filled' => $filled];
        })->all();

        $isSubmitted = $logsheet?->status === LogsheetStatus::Submitted;

        return Inertia::render('operator/logsheet', [
            'filters' => ['unit_id' => $unit->id, 'engine_id' => $engine?->id, 'log_date' => $logDate],
            'stats' => $engine === null ? [] : $this->dayStats($parameters, $logsheet, count(self::SLOTS)),
            'header' => [
                'shift' => $logsheet?->shift,
                'status' => ($logsheet?->status ?? LogsheetStatus::Draft)->value,
            ],
            'parameters' => $parameters->map(fn (LogsheetParameter $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'sub_channel' => $p->sub_channel,
                'label' => $p->label(),
                'unit_of_measure' => $p->unit_of_measure,
            ])->all(),
            'rows' => $rows,
            'slots' => self::SLOTS,
            'shifts' => self::SHIFTS,
            'options' => [
                'units' => $units->all(),
                'machines' => $machines->all(),
            ],
            // The operator can edit only a draft sheet; TL/Manager never write.
            'can_write' => $user->hasPermissionTo(PermissionName::OperatorLogsheetWrite) && ! $isSubmitted,
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

        $validated = $request->validate([
            'log_date' => ['required', 'date'],
            'shift' => ['nullable', Rule::in(self::SHIFTS)],
            'time_slot' => ['required', Rule::in(self::SLOTS)],
            'values' => ['array'],
            'values.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $logDate = Carbon::parse($validated['log_date'])->toDateString();
        $logsheet = $this->findOrNewSheet($engine->id, $logDate);
        abort_if($logsheet->exists && $logsheet->status === LogsheetStatus::Submitted, 422, 'Logsheet sudah dikirim dan terkunci.');

        $validParameterIds = array_flip(LogsheetParameter::query()->where('is_active', true)->pluck('id')->all());

        DB::transaction(function () use ($logsheet, $validated, $unit, $engine, $user, $validParameterIds): void {
            $logsheet->fill(['unit_id' => $unit->id, 'engine_id' => $engine->id, 'input_by' => $user->id]);
            if (array_key_exists('shift', $validated)) {
                $logsheet->shift = $validated['shift'];
            }
            $logsheet->save();

            $logsheet->readings()->where('time_slot', $validated['time_slot'])->delete();
            foreach ($validated['values'] ?? [] as $key => $value) {
                $parameterId = (int) str_replace('p_', '', (string) $key);
                if ($value === null || $value === '' || ! isset($validParameterIds[$parameterId])) {
                    continue;
                }
                $logsheet->readings()->create([
                    'time_slot' => $validated['time_slot'],
                    'parameter_id' => $parameterId,
                    'value' => $value,
                ]);
            }
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

    /** Lock the sheet from further operator edits. */
    public function submit(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetWrite), 403);

        [$unit, $engine] = $this->resolveTarget($request);
        $request->validate(['log_date' => ['required', 'date']]);
        $logDate = Carbon::parse($request->input('log_date'))->toDateString();

        $logsheet = $this->findOrNewSheet($engine->id, $logDate);
        $logsheet->fill(['unit_id' => $unit->id, 'engine_id' => $engine->id, 'input_by' => $user->id]);
        $logsheet->status = LogsheetStatus::Submitted;
        $logsheet->submitted_at = now();
        $logsheet->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengirim logsheet {$engine->name} {$logDate}",
            $logsheet,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logsheet dikirim & dikunci.']);

        return back();
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
}
