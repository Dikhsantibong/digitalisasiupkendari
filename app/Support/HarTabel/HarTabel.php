<?php

namespace App\Support\HarTabel;

use Illuminate\Validation\Rule;

/**
 * Definisi tabel input bebas Modul HAR (satu baris per kejadian per bulan):
 * kop, kolom, total, rekap dan catatan. Satu definisi melayani halaman,
 * validasi, PDF dan tes — tambah tabel dengan subclass di {@see HarTabels::ALL}
 * + route + halaman tipis resources/js/pages/har/input/{key}/index.tsx.
 *
 * Kolom: key, label, type (text|textarea|date|number|select|check), options
 * (select), group (judul di atas kolom bersebelahan), exclusive (kolom check
 * satu grup hanya boleh satu centang per baris), width (px), align (l|c).
 *
 * @phpstan-type Column array{key: string, label: string, type: string, options?: list<string>, group?: string, exclusive?: string, width: int, align: string}
 */
abstract class HarTabel
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * @return list<string>
     */
    abstract public function kopLines(string $unitName, int $month, int $year): array;

    /**
     * @return list<Column>
     */
    abstract public function columns(): array;

    /** Baris kosong yang disiapkan di halaman. */
    public function blankRows(): int
    {
        return 5;
    }

    /**
     * Kolom yang dijumlahkan di baris TOTAL (check = jumlah centang).
     *
     * @return list<string>
     */
    public function totals(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return [];
    }

    public function orientation(): string
    {
        return 'landscape';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{title: string, columns: list<string>, rows: list<list<string|int|float>>}|null
     */
    public function summary(array $rows): ?array
    {
        return null;
    }

    /**
     * Rapikan satu baris kiriman; null bila semua kolom kosong.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string|int|float|null>|null
     */
    public function sanitize(array $row): ?array
    {
        $clean = [];
        $ticked = [];
        foreach ($this->columns() as $column) {
            $value = $row[$column['key']] ?? null;
            $clean[$column['key']] = match ($column['type']) {
                'number' => is_numeric($value) ? $value + 0 : null,
                'check' => (string) $value === '1' ? 1 : null,
                'select' => in_array($value, $column['options'] ?? [], true) ? $value : null,
                'date' => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null,
                default => is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, $column['type'] === 'textarea' ? 2000 : 255) : null,
            };

            // One tick per exclusive group: the first one wins.
            $group = $column['exclusive'] ?? null;
            if ($column['type'] === 'check' && $group !== null && $clean[$column['key']] !== null) {
                if (isset($ticked[$group])) {
                    $clean[$column['key']] = null;
                }
                $ticked[$group] = true;
            }
        }

        return collect($clean)->contains(fn ($value): bool => $value !== null) ? $clean : null;
    }

    /**
     * Jumlah tiap kolom total dari baris tersimpan.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    public function totalValues(array $rows): array
    {
        $totals = [];
        foreach ($this->totals() as $key) {
            $sum = array_sum(array_map(fn (array $row): float => is_numeric($row[$key] ?? null) ? (float) $row[$key] : 0.0, $rows));
            $totals[$key] = floor($sum) === $sum ? (int) $sum : round($sum, 2);
        }

        return $totals;
    }

    /**
     * Validasi dasar kolom (nilai dirapikan lagi oleh sanitize()).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->columns() as $column) {
            $rules["rows.*.{$column['key']}"] = match ($column['type']) {
                'number' => ['nullable', 'numeric', 'between:0,99999999'],
                'check' => ['nullable', 'in:0,1'],
                'select' => ['nullable', Rule::in($column['options'] ?? [])],
                'date' => ['nullable', 'date_format:Y-m-d'],
                'textarea' => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title(),
            'description' => $this->description(),
            'columns' => $this->columns(),
            'totals' => $this->totals(),
            'notes' => $this->notes(),
            'blank_rows' => $this->blankRows(),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return Column
     */
    protected static function col(string $key, string $label, string $type = 'text', int $width = 90, string $align = 'l', array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'width' => $width, 'align' => $align, ...$extra];
    }
}
