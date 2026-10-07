<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\TankFuelType;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operasi\DailyReportStoreRequest;
use App\Models\DailyEngineReport;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperasiIkhtisarSentral;
use App\Models\OperasiIkhtisarSentralMesin;
use App\Models\OperasiRekap;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\IkhtisarSentralSheet;
use App\Services\Operasi\OperasiCalculator;
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
 * Akses 2 — Pengusahaan Operasi (TL & Staf Operasi).
 * Page: pengusahaan/operasi/daily-report/index.
 *
 * Menampilkan dan mengelola input bulanan:
 * 1. Ikhtisar Sentral (KWH Dibangkit, Pemakaian Sendiri, Disalurkan, Beban Puncak, Jam Jalan, dan Tabel per Mesin)
 * 2. Ikhtisar Persediaan Bahan Bakar dan Minyak Pelumas
 */
class PengusahaanDailyReportController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly OperasiCalculator $calculator,
        private readonly IkhtisarSentralSheet $ikhtisarSheet,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView, PermissionName::OperasiLapanganDailyReport), 403);

        $units = Unit::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name']);

        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'fuel_type', 'capacity_kw']);

        // Data jenis pelumas dari master operasi
        $lubricants = LubricantType::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'unit_of_measure']);

        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        // Selected engine untuk backward compatibility
        $engine = $machines->firstWhere('id', (int) $request->integer('engine_id')) ?? $machines->first();

        // Grid data untuk backward compatibility
        $grid = $engine === null
            ? ['rows' => [], 'summary' => []]
            : $this->calculator->buildDailyEngineGrid($engine, $month, $year);

        [$merged, $ikhtisar, $auto, $overrides] = $this->figures($unit, $month, $year, $machines, $lubricants);
        foreach ($merged['mesins'] as $id => $row) {
            $merged['mesins'][$id]['bbm'] = (object) $row['bbm'];
            $merged['mesins'][$id]['pemakaian_pelumas'] = (object) $row['pemakaian_pelumas'];
        }
        foreach ($merged['inventory'] as $row => $values) {
            $merged['inventory'][$row]['bbm'] = (object) $values['bbm'];
            $merged['inventory'][$row]['lubricants'] = (object) $values['lubricants'];
        }
        $merged['mesins'] = (object) $merged['mesins'];

        return Inertia::render('pengusahaan/operasi/daily-report/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'engine_id' => $engine?->id,
                'month' => $month,
                'year' => $year,
            ],
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'machines' => $machines->map(fn (Machine $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'fuel_type' => $m->fuel_type?->value,
                'uses_mfo' => $m->fuel_type?->usesMfo() ?? false,
                'capacity_kw' => $m->capacity_kw !== null ? (float) $m->capacity_kw : null,
            ])->all(),
            'lubricants' => $lubricants->map(fn (LubricantType $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'code' => $l->code,
                'unit_of_measure' => $l->unit_of_measure?->value ?? 'L',
            ])->all(),
            'daysInMonth' => $daysInMonth,
            // Computed from the other sheets; a saved correction wins over the automatic value.
            'ikhtisar' => $merged,
            'fuels' => $this->ikhtisarSheet->fuels($unit),
            'auto' => (object) $auto,
            'edited' => array_keys($overrides),
            'saved_at' => $ikhtisar?->updated_at?->toIso8601String(),
            'grid' => $grid,
            'engine' => $engine === null ? null : [
                'id' => $engine->id,
                'name' => $engine->name,
                'fuel_type' => $engine->fuel_type?->value,
                'uses_mfo' => $engine->fuel_type?->usesMfo() ?? false,
            ],
            'period' => [
                'total_days' => $daysInMonth,
                'locked' => $period?->isLocked() ?? false,
            ],
            'options' => [
                'units' => $units->all(),
                'machines' => $machines->map(fn (Machine $machine): array => [
                    'id' => $machine->id,
                    'name' => $machine->name,
                    'uses_mfo' => $machine->fuel_type?->usesMfo() ?? false,
                ])->all(),
                'years' => range($year - 2, $year + 1),
            ],
            'can_write' => $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite, PermissionName::OperasiLapanganDailyReport),
        ]);
    }

    /**
     * The Ikhtisar Sentral figures of a month: computed from the other sheets
     * ({@see IkhtisarSentralSheet}), a saved correction winning over the
     * automatic value, plus the typed-in rows.
     *
     * @param  Collection<int, Machine>  $machines
     * @param  Collection<int, LubricantType>  $lubricants
     * @return array{0: array{summary: array<string, float>, mesins: array<int, array<string, mixed>>, inventory: array<string, array{bbm: array<string, float>, lubricants: array<int|string, float>}>}, 1: OperasiIkhtisarSentral|null, 2: array<string, float>, 3: array<string, float>}
     */
    private function figures(Unit $unit, int $month, int $year, Collection $machines, Collection $lubricants): array
    {
        // Ambil data ikhtisar sentral yang tersimpan
        $ikhtisar = OperasiIkhtisarSentral::query()
            ->with('mesins')
            ->where('unit_id', $unit->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $stored = $ikhtisar === null ? null : [
            'kwh_pemakaian_sendiri' => (float) $ikhtisar->kwh_pemakaian_sendiri,
            'beban_puncak_pagi_kw' => (float) $ikhtisar->beban_puncak_pagi_kw,
            'beban_puncak_malam_kw' => (float) $ikhtisar->beban_puncak_malam_kw,
            'jam_jalan_perhari' => (float) ($ikhtisar->jam_jalan_perhari ?: 24),
            'persediaan_awal' => $ikhtisar->persediaan_awal ?? [],
            'penerimaan' => $ikhtisar->penerimaan ?? [],
            'penerimaan_sewa_smp' => $ikhtisar->penerimaan_sewa_smp ?? [],
            'pemakaian_non_operasi' => $ikhtisar->pemakaian_non_operasi ?? [],
            'pengiriman' => $ikhtisar->pengiriman ?? [],
        ];
        $machineRefs = $machines->map(fn (Machine $m): array => ['id' => $m->id])->all();
        $lubricantRefs = $lubricants->map(fn (LubricantType $l): array => ['id' => $l->id])->all();
        $auto = $this->ikhtisarSheet->auto($unit, $month, $year, $machineRefs, $lubricantRefs);
        $overrides = $this->ikhtisarSheet->overrides($unit, $month, $year);

        $merged = [
            'summary' => [
                'kwh_pemakaian_sendiri' => 0.0,
                'beban_puncak_pagi_kw' => $stored['beban_puncak_pagi_kw'] ?? 0.0,
                'beban_puncak_malam_kw' => 0.0,
                'jam_jalan_perhari' => $stored['jam_jalan_perhari'] ?? 24.0,
            ],
            'mesins' => [],
            'inventory' => [],
        ];
        foreach (['persediaan_awal', 'penerimaan', 'penerimaan_sewa_smp', 'pemakaian_non_operasi', 'pengiriman'] as $row) {
            $merged['inventory'][$row] = [
                'bbm' => array_map('floatval', (array) ($stored[$row]['bbm'] ?? [])),
                'lubricants' => array_map('floatval', (array) ($stored[$row]['lubricants'] ?? [])),
            ];
        }
        $savedMesins = $ikhtisar?->mesins->keyBy('engine_id');
        foreach ($machines as $machine) {
            $merged['mesins'][$machine->id] = ['kwh_dibangkit' => 0.0, 'jam_jalan' => 0.0, 't_kalor' => 0.0, 'bbm' => [], 'pemakaian_pelumas' => []];
            if ($savedMesins?->has($machine->id)) {
                $merged['mesins'][$machine->id]['t_kalor'] = (float) $savedMesins[$machine->id]->t_kalor;
            }
        }
        // bbm / lubricants / pemakaian_pelumas are keyed by code or id: set them as one bucket.
        foreach ($auto as $path => $value) {
            if (preg_match('/^(.+\.(?:bbm|lubricants|pemakaian_pelumas))\.([^.]+)$/', $path, $match) === 1) {
                $bucket = (array) data_get($merged, $match[1], []);
                $bucket[$match[2]] = $overrides[$path] ?? $value;
                data_set($merged, $match[1], $bucket);

                continue;
            }
            data_set($merged, $path, $overrides[$path] ?? $value);
        }

        return [$merged, $ikhtisar, $auto, $overrides];
    }

    /**
     * Ikhtisar Sentral as a printable page (also embedded in the Laporan Pengusahaan).
     */
    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView, PermissionName::OperasiLapanganDailyReport) || $user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = max(2020, min(2100, (int) ($request->integer('year') ?: $now->year)));
        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'fuel_type', 'capacity_kw']);
        $lubricants = LubricantType::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code', 'unit_of_measure']);
        [$merged] = $this->figures($unit, $month, $year, $machines, $lubricants);

        $pdf = Pdf::loadView('operasi.ikhtisar-sentral.pdf', [
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'lubricants' => $lubricants,
            'fuels' => $this->ikhtisarSheet->fuels($unit),
            'ikhtisar' => $merged,
        ])->setPaper('a4', 'landscape');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("Ikhtisar_Sentral_{$unit->name}_{$month}_{$year}.pdf");
    }

    public function store(DailyReportStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite, PermissionName::OperasiLapanganDailyReport), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $month = $request->integer('month');
        $year = $request->integer('year');

        $period = $this->resolvePeriod($unit->id, $month, $year);
        abort_if($period->isLocked(), 422, 'Periode ini sudah dikunci.');

        DB::transaction(function () use ($request, $unit, $user, $month, $year): void {
            $summary = $request->input('summary', []);
            $inventory = $request->input('inventory', []);

            // 1. Simpan atau perbarui data Ikhtisar Sentral
            $ikhtisar = OperasiIkhtisarSentral::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
                [
                    'kwh_dibangkit' => (float) ($summary['kwh_dibangkit'] ?? 0),
                    'kwh_pemakaian_sendiri' => (float) ($summary['kwh_pemakaian_sendiri'] ?? 0),
                    'kwh_disalurkan' => (float) ($summary['kwh_disalurkan'] ?? 0),
                    'beban_puncak_pagi_kw' => (float) ($summary['beban_puncak_pagi_kw'] ?? 0),
                    'beban_puncak_malam_kw' => (float) ($summary['beban_puncak_malam_kw'] ?? 0),
                    'jam_jalan_perhari' => (float) ($summary['jam_jalan_perhari'] ?? 24),
                    'persediaan_awal' => $inventory['persediaan_awal'] ?? null,
                    'penerimaan' => $inventory['penerimaan'] ?? null,
                    'penerimaan_sewa_smp' => $inventory['penerimaan_sewa_smp'] ?? null,
                    'pemakaian_non_operasi' => $inventory['pemakaian_non_operasi'] ?? null,
                    'pengiriman' => $inventory['pengiriman'] ?? null,
                    'input_by' => $user->id,
                ]
            );

            // 2. Simpan atau perbarui data per mesin
            foreach ($request->input('mesins', []) as $m) {
                if (! isset($m['engine_id'])) {
                    continue;
                }

                OperasiIkhtisarSentralMesin::query()->updateOrCreate(
                    [
                        'ikhtisar_sentral_id' => $ikhtisar->id,
                        'engine_id' => (int) $m['engine_id'],
                    ],
                    [
                        'unit_id' => $unit->id,
                        'kwh_dibangkit' => (float) ($m['kwh_dibangkit'] ?? 0),
                        'jam_jalan' => (float) ($m['jam_jalan'] ?? 0),
                        'pemakaian_bbm' => array_map('floatval', (array) ($m['bbm'] ?? [])),
                        // Legacy totals (dashboard): the BBM of each tank group; older clients post them directly.
                        'pemakaian_hsd' => isset($m['bbm']) ? $this->litersOfGroup((array) $m['bbm'], TankFuelType::Hsd) : (float) ($m['pemakaian_hsd'] ?? 0),
                        'pemakaian_mfo' => isset($m['bbm']) ? $this->litersOfGroup((array) $m['bbm'], TankFuelType::Mfo) : (float) ($m['pemakaian_mfo'] ?? 0),
                        'sfc' => (float) ($m['sfc'] ?? 0),
                        't_kalor' => (float) ($m['t_kalor'] ?? 0),
                        'slc' => (float) ($m['slc'] ?? 0),
                        'pemakaian_pelumas' => $m['pemakaian_pelumas'] ?? [],
                    ]
                );
            }

            // 3. Dukungan kompatibilitas penyimpanan harian jika rows dikirim
            if ($request->has('rows') && $request->filled('engine_id')) {
                $engine = Machine::query()->where('unit_id', $unit->id)->find($request->integer('engine_id'));
                if ($engine) {
                    $usesMfo = $engine->fuel_type?->usesMfo() ?? false;
                    foreach ($request->array('rows') as $row) {
                        $date = Carbon::create($year, $month, (int) $row['day']);
                        $attributes = collect($row)
                            ->only(OperasiCalculator::EDITABLE_FIELDS)
                            ->map(fn ($value) => $value === '' ? null : $value)
                            ->all();

                        if (! $usesMfo) {
                            $attributes['flowmeter_mfo_stand_akhir'] = null;
                            $attributes['flowmeter_mfo_tambah_liter'] = null;
                        }

                        DailyEngineReport::query()->updateOrCreate(
                            ['engine_id' => $engine->id, 'report_date' => $date->toDateString()],
                            [...$attributes, 'unit_id' => $unit->id, 'input_by' => $user->id],
                        );
                    }
                }
            }
        });

        $this->rememberCorrections($request, $unit, $month, $year, $user->id);

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Ikhtisar Sentral & Persediaan {$unit->name} periode {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data Ikhtisar Sentral & Persediaan berhasil disimpan.']);

        return back();
    }

    /**
     * Keep which computed figures the user corrected, so the others keep
     * following their source sheets.
     */
    private function rememberCorrections(DailyReportStoreRequest $request, Unit $unit, int $month, int $year, int $userId): void
    {
        $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->get(['id'])->map(fn (Machine $m): array => ['id' => $m->id])->all();
        $lubricants = LubricantType::query()->where('unit_id', $unit->id)->where('is_active', true)->get(['id'])->map(fn (LubricantType $l): array => ['id' => $l->id])->all();

        $payload = [
            'summary' => (array) $request->input('summary', []),
            'mesins' => collect($request->input('mesins', []))->keyBy('engine_id')->all(),
            'inventory' => (array) $request->input('inventory', []),
        ];

        OperasiRekap::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => IkhtisarSentralSheet::JENIS, 'month' => $month, 'year' => $year],
            ['overrides' => $this->ikhtisarSheet->overridesFrom($payload, $this->ikhtisarSheet->auto($unit, $month, $year, $machines, $lubricants)), 'input_by' => $userId],
        );
    }

    /**
     * Liters of the jenis BBM whose code is the tank group's (e.g. HSD), for
     * the legacy per-group columns.
     *
     * @param  array<string, mixed>  $bbm
     */
    private function litersOfGroup(array $bbm, TankFuelType $group): float
    {
        return (float) collect($bbm)->filter(fn ($value, $code): bool => strcasecmp((string) $code, $group->label()) === 0)->sum(fn ($value): float => (float) $value);
    }

    private function resolvePeriod(int $unitId, int $month, int $year): ReportPeriod
    {
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;

        return ReportPeriod::query()->firstOrCreate(
            ['unit_id' => $unitId, 'month' => $month, 'year' => $year],
            ['total_days' => $totalDays, 'total_hours' => $totalDays * 24],
        );
    }
}
