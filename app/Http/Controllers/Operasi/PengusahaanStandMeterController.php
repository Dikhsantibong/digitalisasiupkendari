<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\CalibrationFactor;
use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\OperasiStandMeter;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Operasi\UnitFuelTypes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanStandMeterController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = $units->firstWhere('id', $unitId) ?? $units->first();

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        // Jenis BBM of this unit, from its master (Tangki BBM & BBM mesin).
        $availableFuels = $this->unitFuels($unit);
        $codes = array_column($availableFuels, 'code');
        $requested = strtoupper($request->string('fuel')->trim()->value());
        $fuelName = in_array($requested, $codes, true) ? $requested : ($codes[0] ?? 'HSD');

        // Query existing record
        $record = OperasiStandMeter::query()
            ->where('unit_id', $unit->id)
            ->where('fuel_name', $fuelName)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;

        if ($record) {
            $machineParameters = $record->machine_parameters ?? [];
            $readings = $record->readings ?? [];
            $totals = $record->totals ?? [];
            $catatan = $record->catatan ?? '';
            $hasSaved = true;
        } else {
            // Compute initial defaults
            $startOfMonth = Carbon::create($year, $month, 1)->toDateString();
            $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
            $standCol = str_contains($fuelName, 'MFO') ? 'flowmeter_mfo_stand_akhir' : 'flowmeter_hsd_stand_akhir';

            $machineParameters = [];
            foreach ($machines as $mach) {
                $prevStand = DailyEngineReport::query()
                    ->where('engine_id', $mach->id)
                    ->where('report_date', '<', $startOfMonth)
                    ->whereNotNull($standCol)
                    ->orderByDesc('report_date')
                    ->value($standCol);

                $prevClosing = (float) ($prevStand ?? 0.0);

                $calFactor = CalibrationFactor::query()
                    ->where('unit_id', $unit->id)
                    ->where(function ($q) use ($mach) {
                        $q->where('engine_id', $mach->id)->orWhereNull('engine_id');
                    })
                    ->where('effective_date', '<=', $endOfMonth)
                    ->orderByRaw('engine_id IS NULL')
                    ->orderByDesc('effective_date')
                    ->value('value');

                $machineParameters[] = [
                    'id' => $mach->id,
                    'name' => $mach->name,
                    'stand_awal_bln_lalu' => round($prevClosing, 2),
                    'faktor_koreksi' => $calFactor !== null ? round((float) $calFactor, 7) : 1.0,
                    'faktor_kali' => 1.0,
                ];
            }

            // Generate rows 1..daysInMonth
            $readings = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayMachs = [];
                foreach ($machines as $mach) {
                    $dayMachs[$mach->id] = [
                        'machine_id' => $mach->id,
                        'awal' => $d === 1 ? (float) ($machineParameters[array_search($mach->id, array_column($machineParameters, 'id'))]['stand_awal_bln_lalu'] ?? 0) : 0,
                        'akhir' => 0,
                        'pemakaian' => 0,
                    ];
                }
                $readings[] = [
                    'tgl' => $d,
                    'machines' => $dayMachs,
                    'adm' => 0,
                    'real' => 0,
                    'selisih' => 0,
                ];
            }

            $machTotals = [];
            foreach ($machines as $mach) {
                $machTotals[$mach->id] = ['awal' => 0, 'akhir' => 0, 'pemakaian' => 0];
            }
            $totals = [
                'machines' => $machTotals,
                'adm' => 0,
                'real' => 0,
                'selisih' => 0,
            ];
            $catatan = '';
            $hasSaved = false;
        }

        return Inertia::render('pengusahaan/operasi/stand-meter/index', [
            'unit' => $unit,
            'units' => $units,
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
                'fuel' => $fuelName,
            ],
            'fuel_name' => $fuelName,
            'available_fuels' => $availableFuels,
            'machine_parameters' => $machineParameters,
            'readings' => $readings,
            'totals' => $totals,
            'catatan' => $catatan,
            'has_saved' => $hasSaved,
            'can_manage' => $codes !== [] && $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite),
            'can_manage_master' => $user->hasPermissionTo(PermissionName::OperasiMasterManage),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020'],
            'fuel_name' => ['required', 'string', 'max:50'],
            'machine_parameters' => ['nullable', 'array'],
            'readings' => ['nullable', 'array'],
            'totals' => ['nullable', 'array'],
            'catatan' => ['nullable', 'string'],
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $request->validate([
            'fuel_name' => [Rule::in(array_column($this->unitFuels($unit), 'code'))],
        ], [
            'fuel_name.in' => 'Jenis BBM tidak terdaftar di Data Master unit ini.',
        ]);

        $record = OperasiStandMeter::updateOrCreate(
            [
                'unit_id' => $unit->id,
                'fuel_name' => strtoupper($validated['fuel_name']),
                'month' => (int) $validated['month'],
                'year' => (int) $validated['year'],
            ],
            [
                'machine_parameters' => $validated['machine_parameters'] ?? [],
                'readings' => $validated['readings'] ?? [],
                'totals' => $validated['totals'] ?? [],
                'catatan' => $validated['catatan'] ?? null,
                'input_by' => $user->id,
            ]
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "Stand Flow Meter BBM {$record->fuel_name} periode {$record->month}/{$record->year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', "Stand Flow Meter BBM {$record->fuel_name} berhasil disimpan.");
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView) ||
            $user->hasPermissionTo(PermissionName::OperasiLaporanView),
            403
        );

        $unitId = (int) $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = max(1, min(12, (int) $request->integer('month', Carbon::now()->month)));
        $year = (int) $request->integer('year', Carbon::now()->year);
        $fuelName = strtoupper($request->string('fuel', 'HSD')->trim()->value());

        $record = OperasiStandMeter::query()
            ->where('unit_id', $unit->id)
            ->where('fuel_name', $fuelName)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($monthNames[$month] ?? $month).' '.$year;

        $machineParameters = $record?->machine_parameters ?? [];
        $readings = $record?->readings ?? [];
        $totals = $record?->totals ?? [];
        $catatan = $record?->catatan ?? '';

        $pdf = Pdf::loadView('operasi.stand-meter.pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'period_label' => $periodLabel,
            'fuel_name' => $fuelName,
            'machine_parameters' => $machineParameters,
            'readings' => $readings,
            'totals' => $totals,
            'catatan' => $catatan,
        ])->setPaper('a4', 'landscape');

        $filename = "Stand_Flow_Meter_{$fuelName}_{$unit->name}_{$month}_{$year}.pdf";

        return $pdf->download($filename);
    }

    /**
     * The jenis BBM of the unit (see {@see UnitFuelTypes}).
     *
     * @return list<array{code: string, name: string}>
     */
    private function unitFuels(Unit $unit): array
    {
        return array_map(
            fn (array $fuel): array => ['code' => $fuel['code'], 'name' => $fuel['name']],
            app(UnitFuelTypes::class)->forUnit($unit),
        );
    }
}
