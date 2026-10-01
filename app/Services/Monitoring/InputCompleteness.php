<?php

namespace App\Services\Monitoring;

use App\Models\Unit;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How complete each unit's inputs are for a month, for every entry of the
 * {@see InputCatalog}: one grouped query per input across all units.
 *
 * A daily input counts the days filled against the days due (the whole month
 * once it has passed, up to today in the current month); a per-machine input
 * counts machine-days against active machines × days due; any other input is
 * filled (100%) or not (0%). Nothing is due in a future month (null).
 */
class InputCompleteness
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private readonly InputCatalog $catalog) {}

    /**
     * @param  Collection<int, Unit>  $units
     * @param  list<string>|null  $groups  only these {@see InputCatalog::GROUPS} (null = all)
     * @return array{
     *     state: 'past'|'current'|'future',
     *     days_due: int,
     *     days_in_month: int,
     *     entries: list<array<string, mixed>>,
     *     units: list<array{id: int, name: string, percent: int|null, groups: array<string, int|null>, cells: array<string, array{filled: int, expected: int, percent: int|null, last: string|null}>}>,
     *     groups: array<string, int|null>,
     *     percent: int|null
     * }
     */
    public function build(Collection $units, int $month, int $year, ?Carbon $today = null, ?array $groups = null): array
    {
        $today ??= Carbon::now(PresenceRecorder::TIMEZONE);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = (int) $start->daysInMonth;
        $state = match (true) {
            $end->lt($today->copy()->startOfDay()) => 'past',
            $start->gt($today) => 'future',
            default => 'current',
        };
        $daysDue = match ($state) {
            'past' => $daysInMonth,
            'future' => 0,
            default => (int) $today->day,
        };

        $unitIds = $units->pluck('id')->all();
        $machines = DB::table('machines')->whereIn('unit_id', $unitIds)->where('is_active', true)
            ->selectRaw('unit_id, COUNT(*) as total')->groupBy('unit_id')->pluck('total', 'unit_id');

        $groupKeys = array_values(array_filter(array_keys(InputCatalog::GROUPS), fn (string $g): bool => $groups === null || in_array($g, $groups, true)));
        $entries = array_values(array_filter($this->catalog->entries(), fn (array $e): bool => in_array($e['group'], $groupKeys, true) && Schema::hasTable($e['table'])));
        $cells = [];

        foreach ($entries as $entry) {
            $rows = $this->measure($entry, $unitIds, $month, $year, $start, $end);

            foreach ($units as $unit) {
                $row = $rows[$unit->id] ?? ['filled' => 0, 'last' => null];
                $expected = match ($entry['kind']) {
                    'daily', 'daily_day' => $daysDue,
                    'daily_engine' => $daysDue * (int) ($machines[$unit->id] ?? 0),
                    'count' => $state === 'future' ? 0 : (int) $entry['expected'],
                    default => $state === 'future' ? 0 : 1,
                };
                $filled = (int) $row['filled'];
                $percent = $expected === 0 ? null : (int) min(100, round(min($filled, $expected) / $expected * 100));

                $cells[$unit->id][$entry['key']] = [
                    'filled' => $filled,
                    'expected' => $expected,
                    'percent' => $percent,
                    'last' => $row['last'],
                ];
            }
        }

        $average = function (array $values): ?int {
            $list = array_values(array_filter($values, fn ($v): bool => $v !== null));

            return $list === [] ? null : (int) round(array_sum($list) / count($list));
        };

        $unitRows = $units->map(function (Unit $unit) use ($cells, $entries, $average, $groupKeys): array {
            $groups = [];
            foreach ($groupKeys as $group) {
                $groups[$group] = $average(array_map(
                    fn (array $e): ?int => $cells[$unit->id][$e['key']]['percent'],
                    array_filter($entries, fn (array $e): bool => $e['group'] === $group),
                ));
            }

            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'percent' => $average(array_map(fn (array $e): ?int => $cells[$unit->id][$e['key']]['percent'], $entries)),
                'groups' => $groups,
                'cells' => $cells[$unit->id] ?? [],
            ];
        })->values()->all();

        $groups = [];
        foreach ($groupKeys as $group) {
            $groups[$group] = $average(array_column(array_column($unitRows, 'groups'), $group));
        }

        return [
            'state' => $state,
            'days_due' => $daysDue,
            'days_in_month' => $daysInMonth,
            'entries' => array_map(fn (array $e): array => [
                'key' => $e['key'],
                'group' => $e['group'],
                'label' => $e['label'],
                'period' => $e['period'],
                'kind' => $e['kind'],
                'route' => $e['route'],
                'params' => $e['params'] ?? [],
            ], $entries),
            'units' => $unitRows,
            'groups' => $groups,
            'percent' => $average(array_column($unitRows, 'percent')),
        ];
    }

    /**
     * Filled count & last change per unit for one input.
     *
     * @param  array<string, mixed>  $entry
     * @param  list<int>  $unitIds
     * @return array<int, array{filled: int, last: string|null}>
     */
    private function measure(array $entry, array $unitIds, int $month, int $year, Carbon $start, Carbon $end): array
    {
        $table = $entry['table'];
        $query = DB::table($table)->whereIn("{$table}.unit_id", $unitIds);

        foreach ($entry['where'] ?? [] as $column => $value) {
            $query->where("{$table}.{$column}", $value);
        }

        $range = fn (Builder $q, string $column) => $q->whereBetween("{$table}.{$column}", [$start->toDateString(), $end->toDateString().' 23:59:59']);

        match ($entry['kind']) {
            'month', 'daily_day', 'count' => $query->where("{$table}.year", $year)->where("{$table}.month", $month),
            'year' => $query->where("{$table}.year", $year),
            'date', 'daily', 'daily_engine' => $range($query, $entry['date']),
            'period' => $query->join('report_periods', 'report_periods.id', '=', "{$table}.report_period_id")
                ->where('report_periods.year', $year)->where('report_periods.month', $month),
        };

        $last = $this->hasColumn($table, 'updated_at') ? "MAX({$table}.updated_at)" : 'NULL';

        if ($entry['kind'] === 'daily_engine') {
            // One row per (unit, machine, day), counted per unit.
            $rows = (clone $query)->selectRaw("{$table}.unit_id as unit_id, {$table}.engine_id as engine_id, DATE({$table}.{$entry['date']}) as day")
                ->groupBy("{$table}.unit_id", "{$table}.engine_id", DB::raw("DATE({$table}.{$entry['date']})"))
                ->get()->countBy('unit_id');
            $lasts = $query->selectRaw("{$table}.unit_id as unit_id, {$last} as last")->groupBy("{$table}.unit_id")->pluck('last', 'unit_id');

            return $rows->map(fn (int $count, $unitId): array => ['filled' => $count, 'last' => $this->stamp($lasts[$unitId] ?? null)])->all();
        }

        $count = match ($entry['kind']) {
            'daily' => "COUNT(DISTINCT DATE({$table}.{$entry['date']}))",
            'daily_day' => "COUNT(DISTINCT {$table}.{$entry['day']})",
            'count' => "COUNT(DISTINCT {$table}.{$entry['distinct']})",
            default => 'COUNT(*)',
        };

        return $query->selectRaw("{$table}.unit_id as unit_id, {$count} as filled, {$last} as last")
            ->groupBy("{$table}.unit_id")->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->unit_id => ['filled' => (int) $row->filled, 'last' => $this->stamp($row->last)]])
            ->all();
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->columns["{$table}.{$column}"] ??= Schema::hasColumn($table, $column);
    }

    private function stamp(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse((string) $value)->toIso8601String();
    }
}
