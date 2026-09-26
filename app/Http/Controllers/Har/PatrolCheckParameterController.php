<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\HarPatrolCheckReading;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\HarLembar\HarLembar;
use App\Support\HarPatrolCheckParameter;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Patrol Check Parameter Mesin (Input HAR): pembacaan parameter mesin harian
 * per mesin — engine, generator, trafo, tegangan baterai. Inputan tersendiri
 * (tidak terhubung ke Laporan Patrol Check), dicetak di Laporan Pemeliharaan.
 */
class PatrolCheckParameterController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($user);
        [$units, $unit, $month, $year] = $this->target($request);
        $machines = Machine::query()->where('unit_id', $unit->id)->orderBy('name')->get(['id', 'name', 'unit_id']);
        $machine = $machines->firstWhere('id', $request->integer('machine_id')) ?? $machines->first();
        $readings = $machine ? $this->readings($machine, $month, $year) : [];

        return Inertia::render('har/input/patrol-check-parameter/index', [
            'sheet' => HarPatrolCheckParameter::toArray(),
            'days' => HarLembar::days($month, $year),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'machine_id' => $machine?->id],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range((int) now()->year - 3, (int) now()->year + 1),
                'machines' => $machines->map(fn (Machine $m): array => ['id' => $m->id, 'name' => $m->name])->values()->all(),
            ],
            'readings' => (object) $readings,
            'has_saved' => $readings !== [],
            'can_write' => $this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganPatrolCheckParameter),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganPatrolCheckParameter), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'machine_id' => ['required', 'integer'],
            'readings' => ['present', 'array', 'max:31'],
            'readings.*' => ['array'],
            'readings.*.*' => ['nullable', 'string', 'max:50'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $machine = Machine::query()->where('unit_id', $unit->id)->findOrFail((int) $validated['machine_id']);
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $numeric = collect(HarPatrolCheckParameter::columns())->where('type', 'number')->pluck('label', 'key');

        $clean = [];
        $errors = [];
        foreach ($validated['readings'] as $day => $values) {
            $day = (int) $day;
            if ($day < 1 || $day > $daysInMonth) {
                $errors["readings.{$day}"] = "Tanggal {$day} di luar bulan ini.";

                continue;
            }

            $values = HarPatrolCheckParameter::sanitize($values);
            foreach ($values as $key => $value) {
                if ($numeric->has($key) && ! is_numeric($value)) {
                    $errors["readings.{$day}.{$key}"] = "Tanggal {$day}: {$numeric[$key]} harus berupa angka.";
                }
            }

            if ($values !== []) {
                $clean[$day] = $values;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $period = ['unit_id' => $unit->id, 'machine_id' => $machine->id, 'year' => $year, 'month' => $month];
        DB::transaction(function () use ($period, $clean, $user): void {
            HarPatrolCheckReading::query()->where($period)->delete();
            foreach ($clean as $day => $values) {
                HarPatrolCheckReading::query()->create([...$period, 'day' => $day, 'values' => $values, 'input_by' => $user->id]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            'Menyimpan '.HarPatrolCheckParameter::TITLE." {$unit->name} ".Indonesian::monthName($month)." {$year} ({$machine->name})",
            $unit,
            unit: $unit->id,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => HarPatrolCheckParameter::TITLE.' berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        $this->authorizeView($request->user());
        [, $unit, $month, $year] = $this->target($request);
        $machine = Machine::query()->where('unit_id', $unit->id)->find($request->integer('machine_id'))
            ?? Machine::query()->where('unit_id', $unit->id)->orderBy('name')->first();
        [$view, $data] = $this->pdfView($unit, $month, $year, $machine);

        $filename = sprintf('Patrol_Check_Parameter_%s_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), str_replace(' ', '_', (string) $machine?->name), $month, $year);

        return response(Pdf::loadView($view, $data)->setPaper('a4', 'landscape')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * PDF view & data of one machine's month (blank sheet without a machine or data) — reused by the Laporan Pemeliharaan.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return ['har.input.patrol-check-parameter-pdf', [
            'unit' => $unit,
            'machine' => $machine,
            'periodLabel' => strtoupper(Indonesian::monthName($month)).' '.$year,
            'columns' => HarPatrolCheckParameter::columns(),
            'headerRows' => HarPatrolCheckParameter::headerRows(),
            'widths' => HarPatrolCheckParameter::widthPercents(),
            'days' => HarLembar::days($month, $year),
            'readings' => $machine ? $this->readings($machine, $month, $year) : [],
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * Machines of the unit with readings in the month, by name.
     *
     * @return Collection<int, Machine>
     */
    public function machinesWithReadings(Unit $unit, int $month, int $year): Collection
    {
        $ids = HarPatrolCheckReading::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->distinct()->pluck('machine_id');

        return Machine::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * @return array<int, array<string, string>> day => values
     */
    private function readings(Machine $machine, int $month, int $year): array
    {
        return HarPatrolCheckReading::query()->where('machine_id', $machine->id)->where('year', $year)->where('month', $month)
            ->orderBy('day')->get()
            ->mapWithKeys(fn (HarPatrolCheckReading $reading): array => [$reading->day => $reading->values])
            ->all();
    }

    private function authorizeView(User $user): void
    {
        abort_unless(($this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganPatrolCheckParameter) || $user->hasPermissionTo(PermissionName::HarLaporanView)), 403);
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);
        $now = Carbon::now();

        return [
            $units,
            $unit,
            max(1, min(12, $request->integer('month') ?: (int) $now->month)),
            max(2000, min(2100, $request->integer('year') ?: (int) $now->year)),
        ];
    }
}
