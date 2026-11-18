<?php

namespace App\Services\Reports;

use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\Unit;
use App\Models\User;
use App\Services\Monitoring\InputCatalog;
use App\Services\Monitoring\InputCompleteness;
use App\Support\Indonesian;
use Illuminate\Database\Eloquent\Collection;

/**
 * SLA bulanan per divisi (PRD.md §8.12): every {@see SlaCatalog} criterion
 * scored from the unit's inputs of the month — the kelengkapan of its source
 * inputs ({@see InputCompleteness}), the submitted laporan, or a record count.
 * % Pencapaian < 100% = Denda (bobot/10 × 9% × tagihan bulan berjalan).
 * One payload for the page, the PDF and the Excel.
 */
class SlaReport
{
    public function __construct(
        private readonly InputCompleteness $completeness,
        private readonly ReportWorkflowService $workflows,
    ) {}

    /**
     * SLA is open to the Super Admin only for now (user, 2026-11-17). Set to
     * false to open each divisi's SLA to whoever may view its laporan.
     */
    public const SUPER_ADMIN_ONLY = true;

    /** Whether the user may open the divisi's SLA: the laporan view of each of its modules. */
    public static function allows(User $user, string $divisi): bool
    {
        return isset(SlaCatalog::DIVISI[$divisi])
            && (! self::SUPER_ADMIN_ONLY || $user->isSuperAdmin())
            && collect(SlaCatalog::DIVISI[$divisi][2])->every(fn (string $module): bool => ModuleResume::allows($user, $module));
    }

    /** Whether the user may download the Laporan SLA gabungan: every divisi's SLA. */
    public static function allowsCombined(User $user): bool
    {
        return collect(array_keys(SlaCatalog::DIVISI))->every(fn (string $divisi): bool => self::allows($user, $divisi));
    }

    /**
     * @return array{
     *     divisi: string, number: string, title: string, contract: string, contract_number: string,
     *     unit: array{id: int, name: string}, period: array{month: int, year: int, label: string, state: string},
     *     rows: list<array<string, mixed>>,
     *     total: array{bobot: int, target: int|float, achieved: int|float, percent: int|null, denda_count: int, denda_bobot: int, measured: int}
     * }
     */
    public function build(string $divisi, Unit $unit, int $month, int $year): array
    {
        [$number, $title] = SlaCatalog::DIVISI[$divisi];
        $criteria = SlaCatalog::criteria($divisi);

        $groups = [];
        foreach ($criteria as $criterion) {
            foreach ($criterion[5] as $source) {
                $groups[] = str_starts_with($source, 'report:') ? null : explode(':', $source)[str_starts_with($source, 'group:') ? 1 : 0];
            }
        }
        $groups = array_values(array_unique(array_filter($groups)));
        $all = in_array('*', $groups, true);

        $data = $this->completeness->build(new Collection([$unit]), $month, $year, groups: $all ? null : $groups);
        $state = $data['state'];
        $cells = $data['units'][0]['cells'] ?? [];
        $groupPercents = $data['units'][0]['groups'] ?? [];
        $labels = array_column($data['entries'], 'label', 'key');

        $rows = [];
        foreach ($criteria as $index => $criterion) {
            [$kriteria, $targetText, $eviden, $jenis, $target, $sources] = $criterion;
            $count = (bool) ($criterion[6] ?? false);
            $bobot = SlaCatalog::bobot($jenis);

            $parts = array_map(fn (string $source): array => $this->measure($source, $unit, $month, $year, $cells, $groupPercents, $labels, $data['units'][0]['percent'] ?? null), $sources);
            $measured = $state !== 'future' && $parts !== [] && collect($parts)->contains(fn (array $p): bool => $p['percent'] !== null);

            if (! $measured) {
                $achieved = null;
                $percent = null;
            } elseif ($count) {
                $achieved = array_sum(array_column($parts, 'filled'));
                $percent = (int) min(100, round($achieved / max($target, 1) * 100));
            } else {
                $values = array_values(array_filter(array_column($parts, 'percent'), fn ($v): bool => $v !== null));
                $percent = (int) round(array_sum($values) / count($values));
                $achieved = round($target * $percent / 100, 2);
            }

            $rows[] = [
                'no' => $index + 1,
                'kriteria' => $kriteria,
                'target_kinerja' => $targetText,
                'eviden' => $eviden,
                'jenis' => $jenis,
                'bobot' => $bobot,
                'formula' => "{$bobot}/10 x 9% x Tagihan bulan berjalan",
                'target' => $target,
                'target_percent' => 100,
                'achieved' => $achieved,
                'percent' => $percent,
                'denda' => $percent === null ? null : $percent < 100,
                // count: the sources show their number of records (dokumen), else their kelengkapan %.
                'mode' => $count ? 'count' : 'percent',
                'sources' => $parts,
            ];
        }

        $measuredRows = array_filter($rows, fn (array $row): bool => $row['percent'] !== null);
        $denda = array_filter($measuredRows, fn (array $row): bool => $row['denda'] === true);
        $targetTotal = array_sum(array_column($rows, 'target'));
        $achievedTotal = round(array_sum(array_map(fn (array $row) => min($row['achieved'] ?? 0, $row['target']), $rows)), 2);

        return [
            'divisi' => $divisi,
            'number' => $number,
            'title' => $title,
            'contract' => SlaCatalog::CONTRACT,
            'contract_number' => SlaCatalog::CONTRACT_NUMBER,
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'period' => ['month' => $month, 'year' => $year, 'label' => mb_strtoupper(Indonesian::monthName($month)).' '.$year, 'state' => $state],
            'rows' => $rows,
            'total' => [
                'bobot' => array_sum(array_column($rows, 'bobot')),
                'target' => $targetTotal,
                'achieved' => $achievedTotal,
                'percent' => $measuredRows === [] ? null : (int) round(array_sum(array_column($measuredRows, 'percent')) / count($measuredRows)),
                'denda_count' => count($denda),
                'denda_bobot' => array_sum(array_column($denda, 'bobot')),
                'measured' => count($measuredRows),
            ],
        ];
    }

    /**
     * @param  array<string, array{filled: int, expected: int, percent: int|null, last: string|null}>  $cells
     * @param  array<string, int|null>  $groupPercents
     * @param  array<string, string>  $labels
     * @return array{label: string, filled: int, percent: int|null}
     */
    private function measure(string $source, Unit $unit, int $month, int $year, array $cells, array $groupPercents, array $labels, ?int $overall): array
    {
        if (str_starts_with($source, 'report:')) {
            $module = ReportModule::from(substr($source, 7));
            $status = $this->workflows->status($this->workflows->find($module, $unit->id, $month, $year));
            $submitted = ! in_array($status, [ReportStatus::Draft, ReportStatus::Ditolak], true);

            return ['label' => $module->label(), 'filled' => $submitted ? 1 : 0, 'percent' => $submitted ? 100 : 0];
        }

        if (str_starts_with($source, 'group:')) {
            $group = substr($source, 6);

            return [
                'label' => 'Kelengkapan input '.($group === '*' ? 'seluruh divisi' : (InputCatalog::GROUPS[$group] ?? $group)),
                'filled' => 0,
                'percent' => $group === '*' ? $overall : ($groupPercents[$group] ?? null),
            ];
        }

        $cell = $cells[$source] ?? null;

        return ['label' => $labels[$source] ?? $source, 'filled' => (int) ($cell['filled'] ?? 0), 'percent' => $cell['percent'] ?? null];
    }
}
