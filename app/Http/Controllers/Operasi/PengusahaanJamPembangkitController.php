<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\JamMesinJenis;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiJamMesin;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\JamMesinSheet;
use App\Support\Indonesian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Jam Pembangkit: Jam Operasi, Jam Pemeliharaan (PO),
 * Jam MO, Jam Gangguan and Jam Standby of every mesin in one sheet (a column
 * each) with their TOTAL, which must be 24 jam on every filled day. Saved into
 * one {@see OperasiJamMesin} record per jenis — the records the separate
 * sheets, the Laporan Pengusahaan, the Ikhtisar and Kinerja read; Jam Siap
 * Operasi stays 24 jam minus OPS, PO, MO and GGN.
 */
class PengusahaanJamPembangkitController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly JamMesinSheet $sheet,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $machines = $this->sheet->machines($unit);

        $readings = [];
        $starStop = [];
        $savedAt = null;
        $catatan = '';
        foreach (JamMesinJenis::cases() as $jenis) {
            $readings[$jenis->value] = (object) $this->sheet->readings($unit, $jenis, $month, $year, $machines);
            $starStop[$jenis->value] = (object) $this->sheet->fromStarStop($unit, $jenis, $month, $year, $machines);
            $record = $this->sheet->record($unit, $jenis, $month, $year);
            $catatan = $catatan !== '' ? $catatan : (string) ($record?->catatan ?? '');
            if ($record !== null && ($savedAt === null || $record->updated_at?->gt($savedAt))) {
                $savedAt = $record->updated_at;
            }
        }

        return Inertia::render('pengusahaan/operasi/jam-pembangkit/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'jenis' => collect(JamMesinJenis::cases())->map(fn (JamMesinJenis $jenis): array => ['value' => $jenis->value, 'label' => $jenis->label()])->all(),
            'readings' => $readings,
            'star_stop' => $starStop,
            'catatan' => $catatan,
            'saved_at' => $savedAt?->toIso8601String(),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'readings' => ['nullable', 'array'],
            'readings.*' => ['array'],
            'readings.*.*' => ['array'],
            'readings.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'readings.*.*.*.max' => 'Jam per hari paling banyak 24.',
            'readings.*.*.*.min' => 'Jam tidak boleh negatif.',
            'readings.*.*.*.numeric' => 'Isian harus berupa angka.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $machines = $this->sheet->machines($unit);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $sheets = [];
        foreach (JamMesinJenis::cases() as $jenis) {
            $sheets[$jenis->value] = $this->sheet->sanitize($validated['readings'][$jenis->value] ?? [], $machines, $daysInMonth);
        }

        // A filled day of one mesin is Operasi + PO + MO + Gangguan + Standby = exactly 24 jam.
        $errors = [];
        foreach ($machines as $machine) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $hours = array_map(fn (array $sheet): ?float => $sheet[$machine['id']][$day] ?? null, $sheets);
                if (array_filter($hours, fn (?float $value): bool => $value !== null) === []) {
                    continue;
                }

                $total = round(array_sum(array_map('floatval', $hours)), 2);
                if (abs($total - JamMesinSheet::HOURS_PER_DAY) > 0.001) {
                    $errors["readings.operasi.{$machine['id']}.{$day}"] = "{$machine['name']} tanggal {$day}: total OPS + PO + MO + GGN + Standby {$total} jam, harus 24 jam.";
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($sheets, $machines, $unit, $month, $year, $validated, $user): void {
            foreach (JamMesinJenis::cases() as $jenis) {
                $summary = $this->sheet->summarize($sheets[$jenis->value], $machines);

                OperasiJamMesin::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'jenis' => $jenis, 'month' => $month, 'year' => $year],
                    [
                        'readings' => $sheets[$jenis->value],
                        'totals_by_machine' => $summary['totals_by_machine'],
                        'grand_total' => $summary['grand_total'],
                        'catatan' => $validated['catatan'] ?? null,
                        'input_by' => $user->id,
                    ],
                );
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Jam Pembangkit {$month}/{$year} disimpan untuk {$unit->name}", $unit, unit: $unit->id);

        return back()->with('success', 'Jam pembangkit berhasil disimpan.');
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            max(2020, min(2100, (int) ($request->integer('year') ?: $now->year))),
        ];
    }
}
