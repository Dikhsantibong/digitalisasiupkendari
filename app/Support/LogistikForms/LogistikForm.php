<?php

namespace App\Support\LogistikForms;

/**
 * Definition of a Logistik & Gudang table form: kop, columns, sections with
 * their default rows, computed columns, section totals, an optional summary
 * table and notes. One definition drives the input page, the store
 * validation, the PDF (resources/views/logistik/input/form-pdf.blade.php),
 * the Excel export and the Laporan Logistik — add a form by adding a
 * subclass to {@see LogistikForms::ALL}.
 *
 * Column: key, label, type (text|textarea|number|select|image|computed),
 * options (select), group (header spanning adjacent columns), width (px),
 * align (l|c|r), default (value of a new row), computed:
 * - ['formula' => 'a + b - c * d'] — + - * / over column keys and numbers;
 * - ['count_filled' => [[keys], …]] — groups with at least one value above 0;
 * - ['percent' => [numerator key, denominator key]].
 *
 * @phpstan-type Column array{key: string, label: string, type: string, options?: list<string>, group?: string, width?: int, align?: string, default?: string|int|float, computed?: array<string, mixed>}
 * @phpstan-type Section array{key: string, title: string|null, rows: list<array<string, string|int|float|null>>, blank_rows: int, totals: list<string>}
 */
abstract class LogistikForm
{
    abstract public function key(): string;

    abstract public function title(): string;

    /** Third kop line. */
    abstract public function kop(): string;

    abstract public function description(): string;

    /**
     * @return list<Column>
     */
    abstract public function columns(): array;

    /**
     * @return list<Section>
     */
    abstract public function sections(): array;

    public function orientation(): string
    {
        return 'landscape';
    }

    /** Minimum PDF row height (px); taller for forms holding photos. */
    public function rowHeight(): int
    {
        return 14;
    }

    /**
     * Lines printed under the table.
     *
     * @return list<string>
     */
    public function notes(): array
    {
        return [];
    }

    /**
     * An optional summary table computed from the saved rows.
     *
     * @param  array<string, list<array<string, mixed>>>  $rows  section key => rows (with computed values)
     * @return array{title: string, columns: list<string>, rows: list<list<string|int|float>>}|null
     */
    public function summary(array $rows): ?array
    {
        return null;
    }

    /**
     * A row with its computed columns filled in.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function compute(array $data): array
    {
        foreach ($this->columns() as $column) {
            if ($column['type'] !== 'computed') {
                continue;
            }

            $spec = $column['computed'] ?? [];
            $data[$column['key']] = match (true) {
                isset($spec['formula']) => $this->round($this->evaluate((string) $spec['formula'], $data)),
                isset($spec['count_filled']) => count(array_filter($spec['count_filled'], fn (array $keys): bool => collect($keys)->contains(fn (string $key): bool => $this->number($data[$key] ?? null) > 0))),
                isset($spec['percent']) => $this->number($data[$spec['percent'][1]] ?? null) > 0
                    ? round($this->number($data[$spec['percent'][0]] ?? null) / $this->number($data[$spec['percent'][1]] ?? null) * 100).'%'
                    : '0%',
                default => null,
            };
        }

        return $data;
    }

    /**
     * Column totals of a section's rows.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    public function totals(string $section, array $rows): array
    {
        $keys = collect($this->sections())->firstWhere('key', $section)['totals'] ?? [];

        return collect($keys)->mapWithKeys(fn (string $key): array => [
            $key => $this->round(array_sum(array_map(fn (array $row): float => $this->number($row[$key] ?? null), $rows))),
        ])->all();
    }

    /**
     * Everything the page and the Excel export need.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title(),
            'kop' => $this->kop(),
            'description' => $this->description(),
            'orientation' => $this->orientation(),
            'columns' => $this->columns(),
            'sections' => array_map(fn (array $s): array => ['key' => $s['key'], 'title' => $s['title'], 'blank_rows' => $s['blank_rows'], 'totals' => $s['totals']], $this->sections()),
            'notes' => $this->notes(),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return Column
     */
    protected static function col(string $key, string $label, string $type = 'text', array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, ...$extra];
    }

    /**
     * @param  list<array<string, string|int|float|null>>  $rows
     * @param  list<string>  $totals
     * @return Section
     */
    protected static function section(string $key, ?string $title, array $rows = [], int $blankRows = 0, array $totals = []): array
    {
        return ['key' => $key, 'title' => $title, 'rows' => $rows, 'blank_rows' => $blankRows, 'totals' => $totals];
    }

    protected function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function round(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : round($value, 2);
    }

    /**
     * Evaluate `a + b * 2 - c` (column keys and numbers; * and / before + and -).
     *
     * @param  array<string, mixed>  $data
     */
    private function evaluate(string $formula, array $data): float
    {
        $tokens = preg_split('/\s+/', trim($formula)) ?: [];
        $value = fn (string $token): float => is_numeric($token) ? (float) $token : $this->number($data[$token] ?? null);

        // First pass: multiply / divide into terms.
        $terms = [$value((string) array_shift($tokens))];
        $signs = [1];
        while ($tokens !== []) {
            $operator = (string) array_shift($tokens);
            $operand = $value((string) array_shift($tokens));
            match ($operator) {
                '*' => $terms[count($terms) - 1] *= $operand,
                '/' => $terms[count($terms) - 1] = $operand != 0.0 ? $terms[count($terms) - 1] / $operand : 0.0,
                default => [$terms[] = $operand, $signs[] = $operator === '-' ? -1 : 1],
            };
        }

        return array_sum(array_map(fn (float $term, int $sign): float => $term * $sign, $terms, $signs));
    }
}
