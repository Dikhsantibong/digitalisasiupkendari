<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\MaintenanceScope;
use App\Enums\PermissionName;
use App\Enums\SchedulePlanType;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akses 2 — Pengusahaan: Rencana & Realisasi pemeliharaan rutin, pengukuran air
 * and monitoring pelumas (six sheets, one per scope × plan type). Each sheet
 * is a month × machine day matrix stored in maintenance_schedules: the day
 * values (P0–P5 codes for HAR, a "v" mark for air / pelumas), the realised
 * durations (HAR realisasi), the operating hours, a remark and a condition
 * note that replaces the day grid when the machine is out (e.g. gangguan).
 */
class ScheduleController extends Controller
{
    use AuthorizesFieldInput;

    /** Static kop of the six sheets (FMKD-314-10.3.1-A1). */
    private const DOCUMENT = ['number' => 'FMKD-314-10.3.1-A1', 'revision' => '01', 'date' => '31 JULI 2024'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'type', 'serial_number']);

        $stored = MaintenanceSchedule::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()
            ->groupBy(fn (MaintenanceSchedule $s): string => $s->scope->value);

        $scopes = collect(MaintenanceScope::cases())->mapWithKeys(fn (MaintenanceScope $scope): array => [
            $scope->value => $machines->map(function (Machine $machine) use ($stored, $scope): array {
                $records = ($stored->get($scope->value) ?? collect())->where('engine_id', $machine->id);

                return [
                    'engine_id' => $machine->id,
                    'name' => $machine->name,
                    'type' => $machine->type,
                    'serial_number' => $machine->serial_number,
                    ...collect(SchedulePlanType::cases())->mapWithKeys(fn (SchedulePlanType $plan): array => [
                        $plan->value => $this->sheet($records->firstWhere('plan_type', $plan)),
                    ])->all(),
                ];
            })->values()->all(),
        ])->all();

        return Inertia::render('pengusahaan/har/rencana-realisasi/index', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'unit_label' => 'UL'.strtoupper($unit->name),
            'days' => $this->days($year, $month),
            'scopes' => $scopes,
            'document' => self::DOCUMENT,
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'sheets' => ['present', 'array'],
            'sheets.*.scope' => ['required', new Enum(MaintenanceScope::class)],
            'sheets.*.plan_type' => ['required', new Enum(SchedulePlanType::class)],
            'sheets.*.rows' => ['present', 'array'],
            'sheets.*.rows.*.engine_id' => ['required', 'integer', Rule::exists('machines', 'id')->where('unit_id', $unit->id)],
            'sheets.*.rows.*.days' => ['nullable', 'array'],
            'sheets.*.rows.*.durasi' => ['nullable', 'array'],
            'sheets.*.rows.*.jam_operasi' => ['nullable', 'string', 'max:50'],
            'sheets.*.rows.*.keterangan' => ['nullable', 'string', 'max:2000'],
            'sheets.*.rows.*.status_note' => ['nullable', 'string', 'max:255'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            foreach ($validated['sheets'] as $sheet) {
                foreach ($sheet['rows'] as $row) {
                    MaintenanceSchedule::query()->updateOrCreate(
                        [
                            'unit_id' => $unit->id, 'year' => $year, 'month' => $month,
                            'engine_id' => (int) $row['engine_id'],
                            'scope' => $sheet['scope'], 'plan_type' => $sheet['plan_type'],
                        ],
                        [
                            'schedule_data' => $this->dayValues($row['days'] ?? []),
                            'durasi_data' => $this->dayValues($row['durasi'] ?? []),
                            'jam_operasi' => $this->text($row['jam_operasi'] ?? null),
                            'keterangan' => $this->text($row['keterangan'] ?? null),
                            'status_note' => $this->text($row['status_note'] ?? null),
                            'input_by' => $user->id,
                        ],
                    );
                }
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan rencana & realisasi pemeliharaan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rencana & realisasi disimpan.']);

        return back();
    }

    /**
     * @return array{days: object, durasi: object, jam_operasi: string, keterangan: string, status_note: string}
     */
    private function sheet(?MaintenanceSchedule $record): array
    {
        return [
            'days' => (object) ($record?->schedule_data ?? []),
            'durasi' => (object) ($record?->durasi_data ?? []),
            'jam_operasi' => (string) ($record?->jam_operasi ?? ''),
            'keterangan' => (string) ($record?->keterangan ?? ''),
            'status_note' => (string) ($record?->status_note ?? ''),
        ];
    }

    /**
     * Day map without blanks, e.g. ['3' => 'P1'].
     *
     * @param  array<int|string, mixed>  $values
     * @return array<string, string>
     */
    private function dayValues(array $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => $value !== null && trim((string) $value) !== '')
            ->mapWithKeys(fn ($value, $day): array => [(string) $day => trim((string) $value)])
            ->all();
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Days of the month; weekends and national holidays are red.
     *
     * @return list<array{day: int, dow: string, is_red: bool, holiday: string|null}>
     */
    private function days(int $year, int $month): array
    {
        $holidays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date', 'description']);

        return collect(range(1, (int) Carbon::create($year, $month, 1)->daysInMonth))
            ->map(function (int $day) use ($year, $month, $holidays): array {
                $date = Carbon::create($year, $month, $day);
                $holiday = $holidays->first(fn (Holiday $h): bool => Carbon::parse($h->date)->day === $day);

                return [
                    'day' => $day,
                    'dow' => $date->locale('id')->isoFormat('dd'),
                    'is_red' => $date->isWeekend() || $holiday !== null,
                    'holiday' => $holiday?->description,
                ];
            })
            ->all();
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarPengusahaanView, PermissionName::HarLapanganSchedule);
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarPengusahaanWrite, PermissionName::HarLapanganSchedule);
    }
}
