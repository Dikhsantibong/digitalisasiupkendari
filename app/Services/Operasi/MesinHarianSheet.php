<?php

namespace App\Services\Operasi;

use App\Enums\MesinHarianJenis;
use App\Enums\StatusCodeCategory;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\OperasiMesinHarian;
use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Beban Tertinggi (the highest load of the day, kW) and Jumlah Kali Gangguan
 * (the number of disturbances of the day) per mesin. Beban Tertinggi keeps a
 * daya mampu per machine (carried from the previous month, else the machine's
 * capacity) and takes the maximum; Jumlah Kali Gangguan sums and can be taken
 * from the Star-Stop gangguan entries, whose times and notes are listed.
 * Tara Kalor (kCal/kWh) is typed in and averaged.
 */
class MesinHarianSheet
{
    /**
     * @return list<array{id: int, name: string, merk: string|null, type: string|null, serial_number: string|null, capacity_kw: float|null}>
     */
    public function machines(Unit $unit): array
    {
        return Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine): array => [
                'id' => $machine->id,
                'name' => $machine->name,
                'merk' => $machine->merk,
                'type' => $machine->type,
                'serial_number' => $machine->serial_number,
                'capacity_kw' => $machine->capacity_kw === null ? null : (float) $machine->capacity_kw,
            ])
            ->all();
    }

    public function record(Unit $unit, MesinHarianJenis $jenis, int $month, int $year): ?OperasiMesinHarian
    {
        return OperasiMesinHarian::query()->where('unit_id', $unit->id)->where('jenis', $jenis)->where('month', $month)->where('year', $year)->first();
    }

    /**
     * @param  array<array-key, mixed>  $readings
     * @param  list<array{id: int}>  $machines
     * @return array<int, array<int, float>>
     */
    public function sanitize(MesinHarianJenis $jenis, array $readings, array $machines, int $daysInMonth): array
    {
        $clean = [];

        foreach ($machines as $machine) {
            $row = $readings[$machine['id']] ?? $readings[(string) $machine['id']] ?? null;
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $day => $value) {
                $day = (int) $day;
                $number = is_numeric($value) ? (float) $value : 0.0;
                $number = $jenis === MesinHarianJenis::KaliGangguan ? (float) (int) round($number) : round($number, 2);

                if ($day >= 1 && $day <= $daysInMonth && $number > 0) {
                    $clean[$machine['id']][$day] = min($number, (float) $jenis->maxValue());
                }
            }

            if (isset($clean[$machine['id']])) {
                ksort($clean[$machine['id']]);
            }
        }

        return $clean;
    }

    /**
     * Per machine and per day: the highest (beban), the sum (gangguan) or
     * the average of the filled cells (tara kalor). The unit figure is the
     * highest daily sum (beban), the month's sum (gangguan) or the average of
     * every filled cell (tara kalor).
     *
     * @param  array<int|string, array<int|string, float>>  $readings
     * @param  list<array{id: int}>  $machines
     * @return array{by_machine: array<int, float>, by_day: array<int, float>, total: float}
     */
    public function summarize(MesinHarianJenis $jenis, array $readings, array $machines, int $daysInMonth): array
    {
        $aggregation = $jenis->aggregation();
        $average = fn (array $values): float => ($filled = array_filter($values, fn (float $value): bool => $value > 0)) === [] ? 0.0 : array_sum($filled) / count($filled);

        $byMachine = [];
        $cells = [];
        foreach ($machines as $machine) {
            $values = array_map('floatval', array_values($readings[$machine['id']] ?? []));
            $cells = [...$cells, ...$values];
            $byMachine[$machine['id']] = round(match ($aggregation) {
                'max' => (float) ($values === [] ? 0 : max($values)),
                'sum' => array_sum($values),
                'average' => $average($values),
            }, 2);
        }

        $byDay = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $values = array_map(fn (array $machine): float => (float) ($readings[$machine['id']][$day] ?? 0), $machines);
            $byDay[$day] = round($aggregation === 'average' ? $average($values) : array_sum($values), 2);
        }

        return [
            'by_machine' => $byMachine,
            'by_day' => $byDay,
            'total' => round(match ($aggregation) {
                'max' => (float) max([0, ...array_values($byDay)]),
                'sum' => array_sum($byDay),
                'average' => $average($cells),
            }, 2),
        ];
    }

    /**
     * Daya mampu per machine: this month's, else the latest earlier month's,
     * else the machine's capacity.
     *
     * @param  list<array{id: int, capacity_kw: float|null}>  $machines
     * @return array<int, float|null>
     */
    public function dayaMampu(Unit $unit, int $month, int $year, array $machines): array
    {
        $saved = $this->record($unit, MesinHarianJenis::BebanTinggi, $month, $year)?->params['daya_mampu'] ?? null;
        $previous = OperasiMesinHarian::query()
            ->where('unit_id', $unit->id)
            ->where('jenis', MesinHarianJenis::BebanTinggi)
            ->where(fn ($query) => $query->where('year', '<', $year)->orWhere(fn ($q) => $q->where('year', $year)->where('month', '<', $month)))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first()?->params['daya_mampu'] ?? [];

        $result = [];
        foreach ($machines as $machine) {
            $value = $saved[$machine['id']] ?? $saved[(string) $machine['id']] ?? $previous[$machine['id']] ?? $previous[(string) $machine['id']] ?? $machine['capacity_kw'];
            $result[$machine['id']] = $value === null ? null : (float) $value;
        }

        return $result;
    }

    /**
     * The Star-Stop gangguan entries of the month: the count per machine per
     * day and a note per entry ("Tgl 11 MAK #1 jam 13:34 trip … jam 14:00
     * start kembali").
     *
     * @param  list<array{id: int, name: string}>  $machines
     * @return array{counts: array<int, array<int, float>>, notes: list<string>}
     */
    public function gangguanFromStarStop(Unit $unit, int $month, int $year, array $machines): array
    {
        $names = array_column($machines, 'name', 'id');
        $logs = EngineStatusLog::query()
            ->with('statusCode')
            ->where('engine_status_logs.unit_id', $unit->id)
            ->whereIn('engine_id', array_keys($names))
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->whereHas('statusCode', fn ($query) => $query->where('category', StatusCodeCategory::Gangguan->value))
            ->orderBy('report_date')
            ->orderBy('start_datetime')
            ->get();

        $counts = [];
        $notes = [];
        foreach ($logs as $log) {
            $day = (int) Carbon::parse($log->report_date)->day;
            $counts[$log->engine_id][$day] = ($counts[$log->engine_id][$day] ?? 0) + 1;

            $start = $log->start_datetime ? Carbon::parse($log->start_datetime)->format('H:i') : null;
            $stop = $log->stop_datetime ? Carbon::parse($log->stop_datetime)->format('H:i') : null;
            $what = trim(($log->statusCode?->label ?? 'gangguan').($log->keterangan ? ', '.$log->keterangan : ''));
            $notes[] = trim(sprintf('Tgl %s %s%s %s%s', Carbon::parse($log->report_date)->format('d/m'), $names[$log->engine_id] ?? '', $start ? " jam {$start}" : '', $what, $stop ? ", jam {$stop} start kembali" : ''));
        }

        return ['counts' => $this->sanitize(MesinHarianJenis::KaliGangguan, $counts, $machines, Carbon::create($year, $month, 1)->daysInMonth), 'notes' => $notes];
    }
}
