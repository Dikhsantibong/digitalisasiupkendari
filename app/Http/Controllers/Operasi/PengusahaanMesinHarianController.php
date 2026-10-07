<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\MesinHarianJenis;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiMesinHarian;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\MesinHarianSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Beban Tertinggi and Jumlah Kali Gangguan per mesin
 * per tanggal. Each route passes its sheet as the `jenis` default.
 */
class PengusahaanMesinHarianController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly MesinHarianSheet $sheet,
    ) {}

    public function index(Request $request, MesinHarianJenis $jenis): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $machines = $this->sheet->machines($unit);
        $record = $this->sheet->record($unit, $jenis, $month, $year);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $starStop = $jenis === MesinHarianJenis::KaliGangguan ? $this->sheet->gangguanFromStarStop($unit, $month, $year, $machines) : null;

        return Inertia::render("pengusahaan/operasi/{$jenis->value}/index", [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'days_in_month' => $daysInMonth,
            'readings' => (object) $this->sheet->sanitize($jenis, $record?->readings ?? [], $machines, $daysInMonth),
            'daya_mampu' => $jenis === MesinHarianJenis::BebanTinggi ? (object) $this->sheet->dayaMampu($unit, $month, $year, $machines) : null,
            'star_stop' => $starStop === null ? null : (object) $starStop['counts'],
            'star_stop_notes' => $starStop['notes'] ?? [],
            'catatan' => $record?->catatan ?? '',
            'saved_at' => $record?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request, MesinHarianJenis $jenis): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'readings' => ['nullable', 'array'],
            'readings.*' => ['array'],
            'readings.*.*' => ['nullable', 'numeric', 'min:0', 'max:'.$jenis->maxValue(), ...($jenis === MesinHarianJenis::KaliGangguan ? ['integer'] : [])],
            'daya_mampu' => ['nullable', 'array'],
            'daya_mampu.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'catatan' => ['nullable', 'string', 'max:4000'],
        ], [
            'readings.*.*.integer' => 'Jumlah gangguan harus bilangan bulat.',
            'readings.*.*.min' => 'Nilai tidak boleh negatif.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $machines = $this->sheet->machines($unit);
        $ids = array_column($machines, 'id');

        $params = null;
        if ($jenis === MesinHarianJenis::BebanTinggi) {
            $params = ['daya_mampu' => collect($validated['daya_mampu'] ?? [])
                ->filter(fn ($value, $id): bool => in_array((int) $id, $ids, true) && $value !== null && $value !== '')
                ->map(fn ($value): float => (float) $value)
                ->all()];
        }

        $record = OperasiMesinHarian::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => $jenis, 'month' => $month, 'year' => $year],
            [
                'readings' => $this->sheet->sanitize($jenis, $validated['readings'] ?? [], $machines, Carbon::create($year, $month, 1)->daysInMonth),
                'params' => $params,
                'catatan' => $validated['catatan'] ?? null,
                'input_by' => $user->id,
            ],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "{$jenis->label()} {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', "{$jenis->label()} berhasil disimpan.");
    }

    public function pdf(Request $request, MesinHarianJenis $jenis): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $machines = $this->sheet->machines($unit);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $record = $this->sheet->record($unit, $jenis, $month, $year);
        $readings = $this->sheet->sanitize($jenis, $record?->readings ?? [], $machines, $daysInMonth);

        $pdf = Pdf::loadView('operasi.mesin-harian.pdf', [
            'unit' => $unit,
            'jenis' => $jenis,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'days_in_month' => $daysInMonth,
            'readings' => $readings,
            'summary' => $this->sheet->summarize($jenis, $readings, $machines, $daysInMonth),
            'daya_mampu' => $jenis === MesinHarianJenis::BebanTinggi ? $this->sheet->dayaMampu($unit, $month, $year, $machines) : [],
            'catatan' => $record?->catatan,
        ])->setPaper('a4', 'portrait');

        $file = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $jenis->label()), '_');
        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("{$file}_{$unit->name}_{$month}_{$year}.pdf");
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
