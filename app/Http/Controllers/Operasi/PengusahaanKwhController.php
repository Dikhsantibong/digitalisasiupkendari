<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\DailyEngineReport;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\KwhSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — kWh: Stand kWh Harian (the only input: the daily PROD
 * and PS kWh meter stands per mesin, kept on DailyEngineReport so the Laporan
 * Pengusahaan and every kWh sheet read the same numbers), Stand kWh Meter
 * untuk Transfer Pricing, kWh Rekap, SFC Netto, Energi Dibangkit and Energi
 * Pemakaian Sendiri.
 */
class PengusahaanKwhController extends Controller
{
    use AuthorizesFieldInput;

    /** @var array<string, array{field: 'nett'|'ps', title: string, heading: string}> */
    private const ENERGI = [
        'dibangkit' => ['field' => 'nett', 'title' => 'Energi Dibangkit', 'heading' => 'ENERGI DIBANGKIT (KWH)'],
        'pemakaian-sendiri' => ['field' => 'ps', 'title' => 'Energi Pemakaian Sendiri', 'heading' => 'ENERGI PEMAKAIAN SENDIRI (KWH)'],
    ];

    /** @var array<string, array{basis: 'produksi'|'nett', heading: string, basis_label: string, decimals: int, doc_no: string, file: string}> */
    private const SFC = [
        'sfc' => ['basis' => 'produksi', 'heading' => 'SPESIFIK FUEL CONSUMPTION (LTR/KWH)', 'basis_label' => 'kWh', 'decimals' => 4, 'doc_no' => 'LK.02.04.46.0904', 'file' => 'SFC'],
        'sfc-netto' => ['basis' => 'nett', 'heading' => 'SPESIFIK FUEL CONSUMPTION NETTO (LTR/KWH)', 'basis_label' => 'kWh Netto', 'decimals' => 3, 'doc_no' => 'LK.02.04.46.0905', 'file' => 'SFC_Netto'],
    ];

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly KwhSheet $sheet,
    ) {}

    public function harian(Request $request): Response
    {
        [$user, $unit, $units, $month, $year] = $this->context($request);

        return Inertia::render('pengusahaan/operasi/stand-kwh/index', [
            ...$this->shared($unit, $units, $month, $year),
            'sheets' => $this->sheet->harian($unit, $month, $year),
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
            'stands' => ['nullable', 'array'],
            'stands.*' => ['array'],
            'stands.*.*.produksi' => ['nullable', 'numeric', 'min:0'],
            'stands.*.*.ps' => ['nullable', 'numeric', 'min:0'],
            'opening' => ['nullable', 'array'],
            'opening.*.produksi' => ['nullable', 'numeric', 'min:0'],
            'opening.*.ps' => ['nullable', 'numeric', 'min:0'],
        ], [
            '*.min' => 'Stand kWh tidak boleh negatif.',
            'stands.*.*.*.min' => 'Stand kWh tidak boleh negatif.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $start = Carbon::create($year, $month, 1);
        $machines = $this->sheet->machines($unit)->keyBy('id');

        DB::transaction(function () use ($validated, $machines, $start, $unit, $user): void {
            foreach ($validated['opening'] ?? [] as $machineId => $opening) {
                // The opening stand is only asked while the machine has no earlier reading.
                $hasPrevious = DailyEngineReport::query()->where('engine_id', $machineId)->where('report_date', '<', $start->toDateString())->exists();
                if (! $machines->has($machineId) || $hasPrevious) {
                    continue;
                }

                $this->saveStand($unit, $user, (int) $machineId, $start->copy()->subDay(), $opening['produksi'] ?? null, $opening['ps'] ?? null);
            }

            foreach ($validated['stands'] ?? [] as $machineId => $days) {
                if (! $machines->has($machineId)) {
                    continue;
                }

                foreach ($days as $day => $stand) {
                    if ((int) $day < 1 || (int) $day > $start->daysInMonth) {
                        continue;
                    }

                    $this->saveStand($unit, $user, (int) $machineId, $start->copy()->day((int) $day), $stand['produksi'] ?? null, $stand['ps'] ?? null);
                }
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Stand kWh Harian {$month}/{$year} disimpan untuk {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        return back()->with('success', 'Stand kWh harian berhasil disimpan.');
    }

    public function harianPdf(Request $request): HttpResponse
    {
        [, $unit, , $month, $year] = $this->context($request, pdf: true);

        return $this->pdf($request, 'operasi.kwh.harian-pdf', [
            'sheets' => $this->sheet->harian($unit, $month, $year),
        ], $unit, $month, $year, 'Stand_kWh_Harian', 'landscape');
    }

    public function transferPricing(Request $request): Response
    {
        [, $unit, $units, $month, $year] = $this->context($request);

        return Inertia::render('pengusahaan/operasi/kwh-tp/index', [
            ...$this->shared($unit, $units, $month, $year),
            ...$this->sheet->transferPricing($unit, $month, $year),
        ]);
    }

    public function transferPricingPdf(Request $request): HttpResponse
    {
        [, $unit, , $month, $year] = $this->context($request, pdf: true);

        return $this->pdf($request, 'operasi.kwh.tp-pdf', $this->sheet->transferPricing($unit, $month, $year), $unit, $month, $year, 'Stand_kWh_TP', 'landscape');
    }

    public function rekap(Request $request): Response
    {
        [, $unit, $units, $month, $year] = $this->context($request);

        return Inertia::render('pengusahaan/operasi/kwh-rekap/index', [
            ...$this->shared($unit, $units, $month, $year),
            ...$this->sheet->rekap($unit, $month, $year),
        ]);
    }

    public function rekapPdf(Request $request): HttpResponse
    {
        [, $unit, , $month, $year] = $this->context($request, pdf: true);

        return $this->pdf($request, 'operasi.kwh.rekap-pdf', $this->sheet->rekap($unit, $month, $year), $unit, $month, $year, 'kWh_Rekap', 'landscape');
    }

    public function sfc(Request $request, string $jenis): Response
    {
        $config = self::SFC[$jenis] ?? abort(404);
        [, $unit, $units, $month, $year] = $this->context($request);

        return Inertia::render("pengusahaan/operasi/{$jenis}/index", [
            ...$this->shared($unit, $units, $month, $year),
            ...$this->sheet->sfc($unit, $month, $year, $config['basis']),
        ]);
    }

    public function sfcPdf(Request $request, string $jenis): HttpResponse
    {
        $config = self::SFC[$jenis] ?? abort(404);
        [, $unit, , $month, $year] = $this->context($request, pdf: true);

        return $this->pdf($request, 'operasi.kwh.sfc-pdf', [
            ...$config,
            ...$this->sheet->sfc($unit, $month, $year, $config['basis']),
        ], $unit, $month, $year, $config['file'], 'portrait');
    }

    public function energi(Request $request, string $jenis): Response
    {
        $config = self::ENERGI[$jenis] ?? abort(404);
        [, $unit, $units, $month, $year] = $this->context($request);

        return Inertia::render("pengusahaan/operasi/energi-{$jenis}/index", [
            ...$this->shared($unit, $units, $month, $year),
            'title' => $config['title'],
            ...$this->sheet->energi($unit, $month, $year, $config['field']),
        ]);
    }

    public function energiPdf(Request $request, string $jenis): HttpResponse
    {
        $config = self::ENERGI[$jenis] ?? abort(404);
        [, $unit, , $month, $year] = $this->context($request, pdf: true);

        return $this->pdf($request, 'operasi.kwh.energi-pdf', [
            'heading' => $config['heading'],
            ...$this->sheet->energi($unit, $month, $year, $config['field']),
        ], $unit, $month, $year, str_replace(' ', '_', $config['title']), 'portrait');
    }

    /**
     * The user, the selected unit (one the user may see), the units for the
     * filter and the period.
     *
     * @return array{0: User, 1: Unit, 2: Collection<int, Unit>, 3: int, 4: int}
     */
    private function context(Request $request, bool $pdf = false): array
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = max(2020, min(2100, (int) ($request->integer('year') ?: $now->year)));

        if ($pdf) {
            $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
            abort_unless($user->canAccessUnit($unit), 403);

            return [$user, $unit, collect(), $month, $year];
        }

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');
        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);

        return [$user, $unit, $units, $month, $year];
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    private function shared(Unit $unit, $units, int $month, int $year): array
    {
        return [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdf(Request $request, string $view, array $data, Unit $unit, int $month, int $year, string $file, string $orientation): HttpResponse
    {
        $pdf = Pdf::loadView($view, [
            ...$data,
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
        ])->setPaper('a4', $orientation);

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("{$file}_{$unit->name}_{$month}_{$year}.pdf");
    }

    /**
     * Write the kWh stands of one machine on one day. Other readings of that
     * DailyEngineReport (BBM, air, …) are left as they are; an empty day with
     * no report is not created.
     */
    private function saveStand(Unit $unit, User $user, int $machineId, Carbon $date, mixed $produksi, mixed $ps): void
    {
        $values = [
            'kwh_produksi_stand_akhir' => blank($produksi) ? null : (float) $produksi,
            'kwh_pakai_sendiri_stand_akhir' => blank($ps) ? null : (float) $ps,
        ];

        // whereDate: SQLite keeps date columns with a time part.
        $report = DailyEngineReport::query()->where('engine_id', $machineId)->whereDate('report_date', $date->toDateString())->first();

        if ($report !== null) {
            $report->fill([...$values, 'input_by' => $user->id])->save();

            return;
        }

        if ($values['kwh_produksi_stand_akhir'] === null && $values['kwh_pakai_sendiri_stand_akhir'] === null) {
            return;
        }

        DailyEngineReport::query()->create([
            ...$values,
            'unit_id' => $unit->id,
            'engine_id' => $machineId,
            'report_date' => $date->toDateString(),
            'input_by' => $user->id,
        ]);
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
}
