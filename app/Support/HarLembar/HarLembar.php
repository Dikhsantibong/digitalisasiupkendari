<?php

namespace App\Support\HarLembar;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * Definisi lembar matriks Modul HAR: kop, kolom teks (sebelum/sesudah grid),
 * kolom grid (minggu, tanggal, atau bulan × minggu), jenis sel, baris per item
 * (satu baris, atau RENCANA + REALISASI), item bawaan per section, legenda,
 * catatan dan rekap. Satu definisi melayani halaman, validasi, PDF dan tes —
 * tambah lembar dengan subclass di {@see HarLembars::ALL} + halaman tipis
 * resources/js/pages/har/{menu}/{key}/index.tsx.
 *
 * Jenis sel: `code` (pilih salah satu kode), `pair` (satu dari dua kode per
 * kolom, mis. N/T per tanggal), `mark` (tanda 1 dilaksanakan).
 *
 * @phpstan-type Field array{key: string, label: string, type: string, position: string, width: int, align: string}
 * @phpstan-type GridColumn array{key: string, label: string, group: string|null, sub: string|null, is_red: bool}
 * @phpstan-type Section array{key: string, title: string|null, items: list<array<string, string|int>>}
 */
abstract class HarLembar
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /** jadwal | input */
    abstract public function menu(): string;

    /**
     * @return list<string>
     */
    abstract public function kopLines(string $unitName, int $month, int $year): array;

    /**
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * @return list<GridColumn>
     */
    abstract public function grid(int $month, int $year): array;

    /** code | pair | mark */
    abstract public function cellType(): string;

    /**
     * Kode sel => arti (opsi & legenda).
     *
     * @return array<string, string>
     */
    abstract public function codes(): array;

    /**
     * @return list<Section>
     */
    abstract public function sections(): array;

    /** Satu dokumen per tahun (disimpan month = 0). */
    public function yearly(): bool
    {
        return false;
    }

    /** Satu dokumen per mesin (subject = id mesin). */
    public function perMachine(): bool
    {
        return false;
    }

    /**
     * Baris sel per item => label.
     *
     * @return array<string, string>
     */
    public function lines(): array
    {
        return ['main' => ''];
    }

    /** Kolom JUMLAH (jumlah sel terisi per baris) dan baris TOTAL. */
    public function showCount(): bool
    {
        return false;
    }

    public function noteLabel(): ?string
    {
        return null;
    }

    public function legendTitle(): string
    {
        return 'Keterangan Kode';
    }

    public function orientation(): string
    {
        return 'landscape';
    }

    /**
     * Rekap dari baris tersimpan.
     *
     * @param  list<array{section: string|null, fields: array<string, mixed>, cells: array<string, array<string, string>>}>  $rows
     * @return array{title: string, columns: list<string>, rows: list<list<string|int>>}|null
     */
    public function summary(array $rows, int $month, int $year): ?array
    {
        return null;
    }

    /**
     * Baris bawaan sebelum ada data tersimpan: teks item saja, sel kosong.
     *
     * @return list<array{section: string|null, fields: array<string, mixed>, cells: array<string, array<string, string>>}>
     */
    public function defaultRows(): array
    {
        $rows = [];
        foreach ($this->sections() as $section) {
            foreach ($section['items'] as $item) {
                $rows[] = ['section' => $section['key'], 'fields' => $this->cleanFields($item), 'cells' => $this->emptyCells()];
            }
        }

        return $rows;
    }

    /**
     * Rapikan satu baris kiriman: section & kolom terdaftar, kode sel yang sah
     * di kolom grid periode ini; null bila baris kosong (tanpa nama & sel).
     *
     * @param  array<string, mixed>  $row
     * @return array{section: string|null, fields: array<string, mixed>, cells: array<string, array<string, string>>}|null
     */
    public function sanitize(array $row, int $month, int $year): ?array
    {
        $sections = array_column($this->sections(), 'key');
        $section = in_array($row['section'] ?? null, $sections, true) ? $row['section'] : ($sections[0] ?? null);
        $fields = $this->cleanFields(is_array($row['fields'] ?? null) ? $row['fields'] : []);

        $columns = array_column($this->grid($month, $year), 'key');
        $cells = [];
        foreach (array_keys($this->lines()) as $line) {
            $submitted = is_array($row['cells'][$line] ?? null) ? $row['cells'][$line] : [];
            $cells[$line] = [];
            foreach ($columns as $column) {
                $value = (string) ($submitted[$column] ?? '');
                if (array_key_exists($value, $this->codes())) {
                    $cells[$line][$column] = $value;
                }
            }
        }

        $name = $fields[$this->fields()[0]['key']] ?? null;
        $hasCells = collect($cells)->contains(fn (array $line): bool => $line !== []);

        return ($name === null || $name === '') && ! $hasCells
            ? null
            : ['section' => $section, 'fields' => $fields, 'cells' => $cells];
    }

    /**
     * Jumlah sel terisi pada satu baris sel, opsional hanya kolom berawalan tertentu.
     *
     * @param  array<string, string>  $cells
     */
    public static function count(array $cells, string $prefix = ''): int
    {
        return count(array_filter(array_keys($cells), fn (string $key): bool => $prefix === '' || str_starts_with($key, $prefix)));
    }

    /**
     * Semua yang dibutuhkan halaman input.
     *
     * @return array<string, mixed>
     */
    public function toArray(int $month, int $year): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title(),
            'description' => $this->description(),
            'menu' => $this->menu(),
            'yearly' => $this->yearly(),
            'per_machine' => $this->perMachine(),
            'fields' => $this->fields(),
            'grid' => $this->grid($month, $year),
            'cell_type' => $this->cellType(),
            'codes' => collect($this->codes())->map(fn (string $label, string $code): array => ['code' => $code, 'label' => $label])->values()->all(),
            'lines' => collect($this->lines())->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
            'sections' => array_map(fn (array $s): array => ['key' => $s['key'], 'title' => $s['title']], $this->sections()),
            'show_count' => $this->showCount(),
            'note_label' => $this->noteLabel(),
            'legend_title' => $this->legendTitle(),
        ];
    }

    /**
     * @return Field
     */
    protected static function field(string $key, string $label, string $type = 'text', string $position = 'before', int $width = 90, string $align = 'l'): array
    {
        return compact('key', 'label', 'type', 'position', 'width', 'align');
    }

    /**
     * Kolom tanggal satu bulan; akhir pekan & hari libur (tabel holidays) merah.
     *
     * @return list<GridColumn>
     */
    public static function days(int $month, int $year): array
    {
        $holidays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date'])
            ->map(fn (Holiday $holiday): int => Carbon::parse($holiday->date)->day)->all();

        return array_map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);

            return [
                'key' => "d{$day}",
                'label' => (string) $day,
                'group' => null,
                'sub' => ['MG', 'SN', 'SL', 'RB', 'KM', 'JM', 'SB'][$date->dayOfWeek],
                'is_red' => $date->isWeekend() || in_array($day, $holidays, true),
            ];
        }, range(1, (int) Carbon::create($year, $month, 1)->daysInMonth));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function emptyCells(): array
    {
        return array_fill_keys(array_keys($this->lines()), []);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string|int|float|null>
     */
    private function cleanFields(array $values): array
    {
        $clean = [];
        foreach ($this->fields() as $field) {
            $value = $values[$field['key']] ?? null;
            $clean[$field['key']] = match (true) {
                $field['type'] === 'number' => is_numeric($value) ? $value + 0 : null,
                is_scalar($value) && trim((string) $value) !== '' => mb_substr(trim((string) $value), 0, 255),
                default => null,
            };
        }

        return $clean;
    }
}
