<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\EmployeePosition;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * An Akses 2 — Pengusahaan HAR technical formulir (Prelube Test, Hydrotest,
 * Clearance Valve …): one record per unit + machine + test date.
 *
 * The page edits only the measured data. The kop (document number, revision,
 * effective date) is static per formulir, the signatories are the unit's
 * Manager UL, Team Leader Pemeliharaan and Staf Pemeliharaan, and the PDF is
 * always rendered from the data by the formulir's PDF builder — no page
 * settings or free-HTML edits.
 */
abstract class HarFormulirController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(protected readonly ActivityLogger $activityLogger) {}

    /** URL / page slug: route har.pengusahaan.{slug}.*, page pengusahaan/har/{slug}/index. */
    abstract protected function slug(): string;

    /** Formulir title, e.g. "Formulir Checklist Prelube Test". */
    abstract protected function title(): string;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** The PDF builder: buildData(Unit, ?Machine, array): array and renderHtml(array): string. */
    abstract protected function builder(): object;

    /** Field permission of Harmes / Harlist for this formulir. */
    abstract protected function lapanganPermission(): PermissionName;

    /**
     * Validation rules of the editable data (besides unit, machine & test date).
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * Input of a formulir not saved yet, including the static kop fields.
     *
     * @return array<string, mixed>
     */
    abstract protected function defaults(Unit $unit, ?Machine $machine, string $testDate): array;

    /** PDF file name prefix. */
    abstract protected function filePrefix(): string;

    /**
     * Adjusts the validated data before it is saved (computed columns, uploads …).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareForSave(array $data, Request $request, ?Model $existing): array
    {
        return $data;
    }

    /**
     * The editable values sent to the page, taken from the builder's view data.
     *
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    protected function formValues(array $viewData): array
    {
        return Arr::only($viewData, array_keys($this->rules()));
    }

    /**
     * Extra page props (e.g. a photo URL).
     *
     * @return array<string, mixed>
     */
    protected function extraProps(?Model $record): array
    {
        return [];
    }

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [$units, $unit] = $this->resolveUnit($request);
        $machines = $this->machines($unit);
        $machine = $machines->firstWhere('id', $request->integer('machine_id')) ?? $machines->first();
        $testDate = $this->testDate($request);
        $record = $this->findRecord($unit, $machine, $testDate);

        $input = $record ? $record->toArray() : $this->defaults($unit, $machine, $testDate);
        $viewData = $this->builder()->buildData($unit, $machine, $this->withSignatories($input, $unit, $user));

        return Inertia::render("pengusahaan/har/{$this->slug()}/index", [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values(),
            'machines' => $machines->map(fn (Machine $m): array => ['id' => $m->id, 'name' => $m->name])->values(),
            'filters' => [
                'unit_id' => $unit->id,
                'machine_id' => $machine?->id,
                'test_date' => $testDate,
            ],
            'form' => $this->formValues($viewData),
            'document' => [
                'title' => $this->title(),
                'number' => $viewData['document_number'] ?? '',
                'revision' => $viewData['revision'] ?? '',
                'effective_date' => $viewData['effective_date'] ?? '',
            ],
            'signatories' => [
                ['title' => $viewData['manager_ul_title'] ?? 'Manager UL', 'name' => $viewData['manager_ul_name'] ?? ''],
                ['title' => $viewData['tl_har_title'] ?? 'Team Leader Pemeliharaan', 'name' => $viewData['tl_har_name'] ?? ''],
                ['title' => $viewData['staff_har_title'] ?? 'Staf Pemeliharaan', 'name' => $viewData['staff_har_name'] ?? ''],
            ],
            'has_saved' => $record !== null,
            'history' => $this->history($unit, $machine),
            'pdf_url' => route("har.pengusahaan.{$this->slug()}.pdf", [
                'unit_id' => $unit->id,
                'machine_id' => $machine?->id,
                'test_date' => $testDate,
            ]),
            'can_write' => $this->canWrite($user),
            ...$this->extraProps($record),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'test_date' => ['required', 'date'],
            ...$this->rules(),
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);
        $machine = Machine::query()->where('unit_id', $unit->id)->findOrFail($validated['machine_id']);
        $testDate = Carbon::parse($validated['test_date'])->format('Y-m-d');

        $modelClass = $this->model();
        $existing = $this->findRecord($unit, $machine, $testDate);
        $data = $this->prepareForSave(Arr::except($validated, ['unit_id', 'machine_id', 'test_date']), $request, $existing);

        // Static kop on the first save; signatories re-resolved from the unit's jabatan holders.
        $kop = $existing ? [] : Arr::only(
            $this->builder()->buildData($unit, $machine, $this->defaults($unit, $machine, $testDate)),
            ['document_number', 'revision', 'effective_date'],
        );
        $signatories = $this->signatoryAttributes($unit, $user);

        $record = $existing ?? new $modelClass(['unit_id' => $unit->id, 'machine_id' => $machine->id, 'test_date' => $testDate]);
        $record->fill([
            ...$kop,
            ...$data,
            ...$signatories,
            'format' => 'form',
            'content_html' => null,
            'created_by' => $existing?->getAttribute('created_by') ?? $user->id,
        ])->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan {$this->title()} {$unit->name} {$machine->name} tanggal {$testDate}",
            $record,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$this->title()} berhasil disimpan."]);

        return to_route("har.pengusahaan.{$this->slug()}.index", [
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
            'test_date' => $testDate,
        ]);
    }

    public function pdf(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user) || $user->hasPermissionTo(PermissionName::HarLaporanView), 403);

        $unit = Unit::query()->with('serviceUnit')->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);
        $machine = Machine::query()->where('unit_id', $unit->id)->find($request->integer('machine_id'));
        $testDate = $this->testDate($request);
        $record = $this->findRecord($unit, $machine, $testDate);

        $input = $record ? $record->toArray() : $this->defaults($unit, $machine, $testDate);
        $viewData = $this->builder()->buildData($unit, $machine, $this->withSignatories($input, $unit, $user));

        $pdf = Pdf::loadHTML($this->builder()->renderHtml($viewData))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $filename = sprintf(
            '%s_%s_%s_%s.pdf',
            $this->filePrefix(),
            str_replace(' ', '_', $unit->name),
            str_replace([' ', '#'], ['_', ''], $machine?->name ?? 'Mesin'),
            Carbon::parse($testDate)->format('Ymd'),
        );

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')."; filename=\"{$filename}\"",
        ]);
    }

    public function destroy(Request $request, int $record): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $modelClass = $this->model();
        $model = $modelClass::query()->findOrFail($record);
        abort_unless($user->canAccessUnit($model->getAttribute('unit_id')), 403);
        $model->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$this->title()} berhasil dihapus."]);

        return back();
    }

    /**
     * Rules of the engine identity block (Merk, Type, No. Seri, No. Mesin, Daya, RPM).
     *
     * @return array<string, mixed>
     */
    protected function machineRules(): array
    {
        return collect(['brand', 'model_type', 'serial_number', 'machine_number', 'installed_power', 'capable_power', 'rpm'])
            ->mapWithKeys(fn (string $key): array => [$key => ['nullable', 'string', 'max:100']])
            ->all();
    }

    /**
     * Rules of a per-cylinder table stored in the JSON column $key.
     *
     * @return array<string, mixed>
     */
    protected function cylinderRules(string $key): array
    {
        return [
            'cylinders_count' => ['required', 'integer', 'between:1,32'],
            $key => ['required', 'array', 'min:1'],
            "{$key}.*" => ['array'],
        ];
    }

    /**
     * Engine identity defaults taken from the machine master.
     *
     * @param  array<string, string>  $fallback
     * @return array<string, mixed>
     */
    protected function machineDefaults(?Machine $machine, array $fallback = []): array
    {
        return [
            'brand' => $fallback['brand'] ?? 'MAK',
            'model_type' => $machine?->type ?: ($fallback['model_type'] ?? '8M 453 AK'),
            'serial_number' => $machine?->serial_number ?: ($fallback['serial_number'] ?? ''),
            'machine_number' => $machine ? trim(str_replace(['MIRRLEES #', 'MESIN #', 'UNIT #', '#'], '', $machine->name)) : ($fallback['machine_number'] ?? '1'),
            'installed_power' => $machine?->capacity_kw ? (string) $machine->capacity_kw : ($fallback['installed_power'] ?? ''),
            'capable_power' => $fallback['capable_power'] ?? '',
            'rpm' => $fallback['rpm'] ?? '600',
        ];
    }

    protected function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarPengusahaanView, $this->lapanganPermission());
    }

    protected function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::HarPengusahaanWrite, $this->lapanganPermission());
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit}
     */
    private function resolveUnit(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->with('serviceUnit')->findOrFail((int) ($request->integer('unit_id') ?: $units->first()->id));
        abort_unless($user->canAccessUnit($unit), 403);

        return [$units, $unit];
    }

    /**
     * @return Collection<int, Machine>
     */
    private function machines(Unit $unit): Collection
    {
        return Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function testDate(Request $request): string
    {
        $raw = (string) $request->input('test_date', '');

        try {
            return $raw !== '' ? Carbon::parse($raw)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::today()->format('Y-m-d');
        }
    }

    private function findRecord(Unit $unit, ?Machine $machine, string $testDate): ?Model
    {
        if ($machine === null) {
            return null;
        }

        $modelClass = $this->model();

        return $modelClass::query()
            ->where('unit_id', $unit->id)
            ->where('machine_id', $machine->id)
            ->whereDate('test_date', $testDate)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, array{id: int, test_date: string, updated_at: string|null}>
     */
    private function history(Unit $unit, ?Machine $machine): array
    {
        if ($machine === null) {
            return [];
        }

        $modelClass = $this->model();

        return $modelClass::query()
            ->where('unit_id', $unit->id)
            ->where('machine_id', $machine->id)
            ->orderByDesc('test_date')
            ->take(12)
            ->get(['id', 'test_date', 'updated_at'])
            ->map(fn (Model $r): array => [
                'id' => (int) $r->getKey(),
                'test_date' => Carbon::parse($r->getAttribute('test_date'))->format('Y-m-d'),
                'updated_at' => $r->getAttribute('updated_at')?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Fills the signatory ids of an input that has none, so the PDF shows the unit's jabatan holders.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function withSignatories(array $input, Unit $unit, User $user): array
    {
        if (! empty($input['manager_ul_id']) || ! empty($input['tl_har_id']) || ! empty($input['staff_har_id']) || ! empty($input['tl_har_name'])) {
            return $input;
        }

        return [...$input, ...$this->signatoryAttributes($unit, $user)];
    }

    /**
     * Manager UL, Team Leader Pemeliharaan and Staf Pemeliharaan of the unit (names & titles follow the employee).
     *
     * @return array<string, int|null>
     */
    private function signatoryAttributes(Unit $unit, User $user): array
    {
        $holder = fn (string $position): ?Employee => $unit->employees()
            ->where('is_active', true)
            ->where('position', $position)
            ->orderBy('id')
            ->first();

        $ownEmployee = $user->employee;
        $staff = $ownEmployee !== null && $ownEmployee->is_active && $ownEmployee->unit_id === $unit->id && $ownEmployee->position === 'Staf Pemeliharaan'
            ? $ownEmployee
            : $holder('Staf Pemeliharaan');

        $signatories = [
            'manager_ul_id' => $unit->manager()?->id,
            'tl_har_id' => $holder(EmployeePosition::TeamLeaderPemeliharaan->value)?->id,
            'staff_har_id' => $staff?->id,
        ];

        // Names & titles are left to the builder (it reads the employee), except stale free-text ones.
        return [
            ...$signatories,
            'manager_ul_name' => null,
            'manager_ul_title' => null,
            'tl_har_name' => null,
            'tl_har_title' => null,
            'staff_har_name' => null,
            'staff_har_title' => null,
        ];
    }
}
