<?php

namespace App\Support\PdmForms;

use App\Models\Machine;
use App\Models\Unit;

/**
 * Definition of a generic PdM input form: its letterhead, header/footer
 * fields, sections (columns + default rows) and optional summary. Signatures
 * are left out until the verification flow is built.
 * One definition drives the input page, the store validation, the PDF
 * (resources/views/pdm/input/{key}-pdf.blade.php), the Excel export and the
 * Laporan PdM — add a form by adding a subclass to {@see PdmForms::ALL}.
 *
 * Column: key, label, type (text|number|date|time|select|readonly|check), options,
 * group (label spanning adjacent columns), unit (sub-header), width, align,
 * exclusive (check columns of one group allow one tick per row, e.g. Ya/Tidak/N/A).
 * Field: key, label, type (text|date|time|number|textarea|images), position
 * (header|footer), group (heading printed above the field block).
 *
 * @phpstan-type Column array{key: string, label: string, type?: string, options?: list<string>, group?: string, unit?: string, width?: int, align?: string, exclusive?: string}
 * @phpstan-type Field array{key: string, label: string, type?: string, position?: string, group?: string}
 * @phpstan-type Section array{key: string, title: string, note?: string, columns: list<Column>, rows?: list<array<string, string|null>>, blank_rows?: int, fixed?: bool, totals?: list<string>}
 */
abstract class PdmForm
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * @return list<string>
     */
    abstract public function kopLines(string $unitName): array;

    /**
     * @return list<Section>
     */
    abstract public function sections(): array;

    /** Letterhead background: plain|cyan|navy. */
    public function theme(): string
    {
        return 'plain';
    }

    /** Table header colour: orange|navy|cyan. */
    public function headerColor(): string
    {
        return 'orange';
    }

    public function orientation(): string
    {
        return 'landscape';
    }

    /** Number rows 1..n across all sections instead of restarting per section. */
    public function continuousNumbering(): bool
    {
        return false;
    }

    /** One document per machine (vibrasi, pelumas) instead of per unit. */
    public function perMachine(): bool
    {
        return false;
    }

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * Default header values before anything is saved.
     *
     * @return array<string, string>
     */
    public function defaults(Unit $unit, int $month, int $year, ?Machine $machine): array
    {
        return [];
    }

    /**
     * An optional summary table computed from the rows, e.g. the 5S5R
     * akumulatif.
     *
     * @param  array<string, list<array<string, string|null>>>  $rows
     * @return array{title: string, columns: list<string>, rows: list<list<string>>}|null
     */
    public function summary(array $rows): ?array
    {
        return null;
    }

    /**
     * @return Section
     */
    public function section(string $key): array
    {
        foreach ($this->sections() as $section) {
            if ($section['key'] === $key) {
                return $section;
            }
        }

        throw new \InvalidArgumentException("Unknown section [{$key}] in PdM form [{$this->key()}].");
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
            'description' => $this->description(),
            'orientation' => $this->orientation(),
            'per_machine' => $this->perMachine(),
            'continuous_numbering' => $this->continuousNumbering(),
            'header_color' => $this->headerColor(),
            'fields' => $this->fields(),
            'sections' => $this->sections(),
        ];
    }

    /**
     * @return Column
     */
    protected static function col(string $key, string $label, string $type = 'text', array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, ...$extra];
    }

    /**
     * @return Field
     */
    protected static function field(string $key, string $label, string $type = 'text', string $position = 'header', ?string $group = null): array
    {
        return array_filter(['key' => $key, 'label' => $label, 'type' => $type, 'position' => $position, 'group' => $group], fn ($v): bool => $v !== null);
    }
}
