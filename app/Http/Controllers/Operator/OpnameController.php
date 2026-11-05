<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\OpnameJenis;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\OperatorOpname;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operator\OpnameSheet;
use App\Support\Indonesian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Opname BBM, Opname Pelumas and Opname kWh (modul Operator): one sheet per
 * unit and tanggal each, its rows from the unit's master ({@see OpnameSheet}).
 * The route gives the jenis through defaults().
 *
 * Uses the logsheet permissions: operators & Project Leader write, TL /
 * Koordinator / Manager read.
 */
class OpnameController extends Controller
{
    /** Master Operasi page of each opname's row names. */
    private const MASTER = [
        'bbm' => 'fuel-tanks',
        'pelumas' => 'lubricant-types',
        'kwh' => 'kwh-meters',
    ];

    public function __construct(
        private readonly OpnameSheet $sheets,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request, string $jenis): Response
    {
        $type = OpnameJenis::from($jenis);
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperatorLogsheetView), 403);

        [$units, $unit] = $this->target($request);
        $tanggal = $this->date($request->string('tanggal')->toString());
        $sections = $this->sheets->sections($unit, $type);
        $values = $this->sheets->values($unit, $tanggal, $type, $sections);
        $record = $this->sheets->record($unit, $tanggal, $type);

        return Inertia::render("operator/opname-{$type->value}/index", [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values(),
            'filters' => ['unit_id' => $unit->id, 'tanggal' => $tanggal],
            'tanggal_label' => Indonesian::dayName(Carbon::parse($tanggal)).', '.Indonesian::longDate(Carbon::parse($tanggal)),
            'sections' => $sections,
            'values' => (object) $values,
            'catatan' => (string) ($record?->catatan ?? ''),
            'saved' => $record ? ['by' => $record->inputUser?->name, 'at' => $record->updated_at?->timezone('Asia/Makassar')->format('d/m/Y H:i')] : null,
            'summary' => $type === OpnameJenis::Kwh ? $this->sheets->kwhSummary($unit, $tanggal, $values) : null,
            'bbm_pemakaian' => $type === OpnameJenis::Kwh ? $this->sheets->bbmPemakaian($unit, $tanggal) : null,
            'master_url' => $user->hasPermissionTo(PermissionName::OperasiMasterViewAny) ? route('operasi.master.index', self::MASTER[$type->value]) : null,
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request, string $jenis): RedirectResponse
    {
        $type = OpnameJenis::from($jenis);
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'values' => ['nullable', 'array'],
            'values.*' => ['array'],
            'values.*.*' => ['array'],
            'values.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'values.*.*.*.numeric' => 'Isi dengan angka.',
            'values.*.*.*.min' => 'Tidak boleh negatif.',
        ]);

        $tanggal = $validated['tanggal'];
        $data = $this->sheets->clean($this->sheets->sections($unit, $type), (array) ($validated['values'] ?? []));
        $catatan = trim((string) ($validated['catatan'] ?? '')) ?: null;
        $record = $this->sheets->record($unit, $tanggal, $type);

        if ($data === [] && $catatan === null) {
            $record?->delete();
        } elseif ($record !== null) {
            $record->update(['data' => $data, 'catatan' => $catatan, 'input_by' => $user->id]);
        } else {
            OperatorOpname::query()->create([
                'unit_id' => $unit->id,
                'tanggal' => $tanggal,
                'jenis' => $type,
                'data' => $data,
                'catatan' => $catatan,
                'input_by' => $user->id,
            ]);
        }

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan {$type->label()} {$unit->name} tanggal ".Carbon::parse($tanggal)->format('d/m/Y'), $unit, unit: $unit->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$type->label()} tersimpan."]);

        return redirect()->route("operator.opname-{$type->value}.index", ['unit_id' => $unit->id, 'tanggal' => $tanggal]);
    }

    private function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperatorLogsheetWrite) && ! $user->isReadOnly();
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit}
     */
    private function target(Request $request): array
    {
        $units = Unit::query()->visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        return [$units, $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first()];
    }

    /** The requested tanggal, today (WITA) when missing or invalid. */
    private function date(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? $value
            : OpnameSheet::today();
    }
}
