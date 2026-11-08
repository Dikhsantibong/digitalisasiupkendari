<?php

namespace App\Services\Dashboard;

use App\Models\HarLubeQuality;
use App\Models\HarVibration;
use App\Models\Machine;
use App\Models\PdmRealisasiPrediktif;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The PdM section of the dashboard, read from the visible units: Realisasi
 * Prediktif (rencana vs realisasi days), the Vibrasi tests (highest V/H max of
 * a test, mm/s; zones after ISO 10816: ≤ 4,5 Normal, ≤ 7,1 Waspada, above that
 * Alarm) and the Kualitas Pelumas tests (status of the latest test).
 */
class PdmDashboard
{
    public const WARNING = 4.5;

    public const ALARM = 7.1;

    /**
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    public function build(Collection $units, Carbon $now): array
    {
        $unitIds = $units->pluck('id')->all();
        $months = DashboardSupport::months($now);
        $last = count($months) - 1;
        $start = Carbon::create($months[0]['year'], $months[0]['month'], 1)->startOfDay();
        $inMonth = fn (?CarbonInterface $date, array $m): bool => $date !== null && $date->month === $m['month'] && $date->year === $m['year'];

        $plans = PdmRealisasiPrediktif::query()->whereIn('unit_id', $unitIds)
            ->where(fn ($q) => $q->where('year', '>', $months[0]['year'])->orWhere(fn ($q2) => $q2->where('year', $months[0]['year'])->where('month', '>=', $months[0]['month'])))
            ->get(['month', 'year', 'rencana', 'realisasi']);
        $rencana = array_map(fn (array $m): int => (int) $plans->where('month', $m['month'])->where('year', $m['year'])->sum(fn (PdmRealisasiPrediktif $p): int => count($p->rencana ?? [])), $months);
        $realisasi = array_map(fn (array $m): int => (int) $plans->where('month', $m['month'])->where('year', $m['year'])->sum(fn (PdmRealisasiPrediktif $p): int => count($p->realisasi ?? [])), $months);
        $percent = array_map(fn (int $plan, int $real): float => $plan > 0 ? round(min(100, $real / $plan * 100), 1) : 0.0, $rencana, $realisasi);

        $tests = HarVibration::query()->whereIn('unit_id', $unitIds)->whereNotNull('test_date')->orderBy('test_date')
            ->get(['id', 'unit_id', 'machine_id', 'test_date', 'measurements'])
            ->map(fn (HarVibration $test): array => [
                'unit_id' => $test->unit_id,
                'machine_id' => $test->machine_id,
                'date' => $test->test_date,
                'points' => count($test->measurements ?? []),
                'max' => $this->highest($test->measurements ?? []),
            ]);
        $lube = HarLubeQuality::query()->whereIn('unit_id', $unitIds)->whereNotNull('test_date')->orderBy('test_date')
            ->get(['unit_id', 'machine_id', 'test_date', 'status_text']);

        $windowTests = $tests->filter(fn (array $t): bool => $t['date']->gte($start));
        $anomalies = array_map(fn (array $m): int => $windowTests->filter(fn (array $t): bool => $inMonth($t['date'], $m) && $t['max'] > self::WARNING)->count(), $months);
        $points = array_map(fn (array $m): int => (int) $windowTests->filter(fn (array $t): bool => $inMonth($t['date'], $m))->sum('points'), $months);
        $samples = array_map(fn (array $m): int => $lube->filter(fn (HarLubeQuality $l): bool => $inMonth($l->test_date, $m))->count(), $months);

        // Latest vibration and lube test per machine.
        $latestVibration = $tests->filter(fn (array $t): bool => $t['machine_id'] !== null)->keyBy('machine_id');
        $latestLube = $lube->filter(fn (HarLubeQuality $l): bool => $l->machine_id !== null)->keyBy('machine_id');
        $machineIds = $latestVibration->keys()->merge($latestLube->keys())->unique()->values();
        $machines = Machine::query()->whereIn('id', $machineIds)->get(['id', 'name', 'unit_id'])->keyBy('id');
        $unitNames = $units->pluck('name', 'id');

        $assets = $machineIds->map(function (int $id) use ($latestVibration, $latestLube, $machines, $unitNames): array {
            $vibration = $latestVibration->get($id);
            $machine = $machines->get($id);

            return [
                'asset' => ($machine?->name ?? 'Mesin').' — '.($unitNames[$machine?->unit_id ?? 0] ?? '—'),
                'vibration' => $vibration['max'] ?? null,
                'oil' => trim(ltrim((string) $latestLube->get($id)?->status_text, '- ')) ?: '—',
                ...$this->zone($vibration['max'] ?? null),
            ];
        })->sortByDesc(fn (array $asset): float => (float) $asset['vibration'])->values();

        // Highest reading per month of the machines tested most recently.
        $series = $windowTests->filter(fn (array $t): bool => $t['machine_id'] !== null)->groupBy('machine_id')
            ->sortByDesc(fn ($group) => $group->last()['date'])->take(3)
            ->map(fn ($group, int $id): array => [
                'name' => $machines->get($id)?->name ?? 'Mesin',
                'values' => array_map(fn (array $m): float => (float) $group->filter(fn (array $t): bool => $inMonth($t['date'], $m))->max('max'), $months),
            ])->values()->all();

        $alarm = $assets->where('status', 'Alarm')->count();

        return [
            'kpis' => [
                DashboardSupport::kpi('titik', 'Titik Ukur', $points[$last], null, 'number', 'up', $points[$last - 1], $points),
                DashboardSupport::kpi('realisasi', 'Realisasi Prediktif', $percent[$last], '%', 'decimal', 'up', $percent[$last - 1], $percent),
                DashboardSupport::kpi('anomali', 'Anomali Terdeteksi', $anomalies[$last], null, 'number', 'down', $anomalies[$last - 1], $anomalies),
                DashboardSupport::kpi('sample', 'Sample Oli Diuji', $samples[$last], null, 'number', 'up', $samples[$last - 1], $samples),
                DashboardSupport::kpi('kritis', 'Aset Status Alarm', $alarm, 'aset', 'number', 'down'),
            ],
            'realisasi' => [
                'labels' => array_column($months, 'label'),
                'series' => [
                    ['name' => 'Rencana', 'values' => $rencana],
                    ['name' => 'Realisasi', 'values' => $realisasi],
                ],
            ],
            'condition' => [
                ['label' => 'Normal', 'value' => $assets->where('status', 'Normal')->count()],
                ['label' => 'Waspada', 'value' => $assets->where('status', 'Waspada')->count()],
                ['label' => 'Alarm', 'value' => $alarm],
            ],
            'vibration' => [
                'labels' => array_column($months, 'label'),
                'threshold' => self::ALARM,
                'series' => $series,
            ],
            'assets' => $assets->take(10)->all(),
        ];
    }

    /**
     * The highest V or H max reading of a test (values typed with a comma).
     *
     * @param  array<int, array<string, mixed>>  $measurements
     */
    private function highest(array $measurements): float
    {
        $max = 0.0;
        foreach ($measurements as $row) {
            foreach (['v_max', 'h_max'] as $field) {
                $value = str_replace(',', '.', (string) ($row[$field] ?? ''));
                if (is_numeric($value)) {
                    $max = max($max, (float) $value);
                }
            }
        }

        return round($max, 2);
    }

    /**
     * @return array{status: string, status_tone: string}
     */
    private function zone(?float $vibration): array
    {
        return match (true) {
            $vibration === null => ['status' => 'Belum Diukur', 'status_tone' => 'neutral'],
            $vibration > self::ALARM => ['status' => 'Alarm', 'status_tone' => 'danger'],
            $vibration > self::WARNING => ['status' => 'Waspada', 'status_tone' => 'warning'],
            default => ['status' => 'Normal', 'status_tone' => 'success'],
        };
    }
}
