<?php

namespace App\Services\Reports;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns a report assembled from embedded PDF-view fragments (Laporan PdM,
 * Laporan Logistik — see {@see ScopedHtmlFragment}) into a spreadsheet grid
 * for the Excel edit mode: a title row per part, then each of its tables cell
 * by cell (colspan/rowspan become merges, `<th>` bold). Tables holding images
 * (the kop) are skipped — the letterhead is added at export time.
 *
 * Expects the builder payload: document.title, report.unit.name,
 * report.period.label and parts[] with title & body.
 *
 * Grid shape: array{name, cols, col_widths, merges: list<array{0..3:int}>,
 *   rows: list<list<array{t:string, b?:bool, a?:string}>>}
 */
class FragmentGridBuilder
{
    /** Widest table kept (a 31-day matrix plus its fixed columns). */
    private const MAX_COLS = 45;

    /** @var list<list<array{t: string, b?: bool, a?: string}>> */
    private array $rows = [];

    /** @var list<array{0: int, 1: int, 2: int, 3: int}> */
    private array $merges = [];

    private int $cols = 1;

    /**
     * @param  array<string, mixed>  $data  the document builder payload
     * @return array<string, mixed>
     */
    public function build(array $data): array
    {
        $this->rows = [];
        $this->merges = [];
        $this->cols = 8;

        $this->title($data['document']['title'].' — '.$data['report']['unit']['name'].' · '.$data['report']['period']['label']);
        $this->rows[] = [$this->cell('')];

        foreach ($data['parts'] as $part) {
            $this->title(strtoupper($part['title']));
            foreach ($this->tables($part['body']) as $table) {
                $this->table($table);
                $this->rows[] = [$this->cell('')];
            }
        }

        // Full-width title rows span every column of the widest table.
        $this->merges = array_map(
            fn (array $merge): array => $merge[3] === -1 ? [$merge[0], 0, $merge[2], $this->cols - 1] : $merge,
            $this->merges,
        );

        return [
            'name' => (string) ($data['document']['grid_name'] ?? 'Laporan'),
            'cols' => $this->cols,
            'col_widths' => array_map(fn (int $i): int => $i === 1 ? 200 : 70, range(0, $this->cols - 1)),
            'merges' => $this->merges,
            'rows' => $this->rows,
        ];
    }

    private function title(string $text): void
    {
        $this->rows[] = [$this->cell($text, true)];
        $row = count($this->rows) - 1;
        $this->merges[] = [$row, 0, $row, -1];
    }

    /**
     * The outermost data tables of a fragment (kop/letterhead tables excluded).
     *
     * @return list<DOMElement>
     */
    private function tables(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $tables = [];
        foreach ((new DOMXPath($document))->query('//table[not(ancestor::table)]') ?: [] as $table) {
            if ($table instanceof DOMElement && $table->getElementsByTagName('img')->length === 0) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    private function table(DOMElement $table): void
    {
        $origin = count($this->rows);
        $occupied = [];
        $rowIndex = 0;

        foreach ((new DOMXPath($table->ownerDocument))->query('.//tr', $table) ?: [] as $tr) {
            if (! $tr instanceof DOMElement || $this->closestTable($tr) !== $table) {
                continue;
            }

            $col = 0;
            foreach ($tr->childNodes as $node) {
                if (! $node instanceof DOMElement || ! in_array($node->tagName, ['td', 'th'], true)) {
                    continue;
                }
                while (isset($occupied[$rowIndex][$col])) {
                    $col++;
                }
                if ($col >= self::MAX_COLS) {
                    break;
                }

                $colspan = max(1, (int) $node->getAttribute('colspan'));
                $rowspan = max(1, (int) $node->getAttribute('rowspan'));
                $colspan = min($colspan, self::MAX_COLS - $col);
                $text = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
                $classes = preg_split('/\s+/', (string) $node->getAttribute('class')) ?: [];
                $align = array_intersect($classes, ['c', 'center', 'text-center']) !== [] ? 'c' : 'l';

                $this->rows[$origin + $rowIndex] ??= [];
                $this->rows[$origin + $rowIndex][$col] = $this->cell($text, $node->tagName === 'th', $node->tagName === 'th' ? 'c' : $align);

                for ($r = 0; $r < $rowspan; $r++) {
                    for ($c = 0; $c < $colspan; $c++) {
                        $occupied[$rowIndex + $r][$col + $c] = true;
                    }
                }
                if ($colspan > 1 || $rowspan > 1) {
                    $this->merges[] = [$origin + $rowIndex, $col, $origin + $rowIndex + $rowspan - 1, $col + $colspan - 1];
                }

                $col += $colspan;
                $this->cols = max($this->cols, $col);
            }
            $rowIndex++;
        }

        // Fill the gaps so every row is a dense list of cells.
        $lastRow = $origin + max($rowIndex, count($occupied)) - 1;
        for ($r = $origin; $r <= $lastRow; $r++) {
            $cells = $this->rows[$r] ?? [];
            $width = $cells === [] ? 0 : max(array_keys($cells)) + 1;
            $dense = [];
            for ($c = 0; $c < $width; $c++) {
                $dense[] = $cells[$c] ?? $this->cell('');
            }
            $this->rows[$r] = $dense === [] ? [$this->cell('')] : $dense;
        }
        ksort($this->rows);
        $this->rows = array_values($this->rows);
    }

    private function closestTable(DOMElement $element): ?DOMElement
    {
        for ($node = $element->parentNode; $node !== null; $node = $node->parentNode) {
            if ($node instanceof DOMElement && $node->tagName === 'table') {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return array{t: string, b?: bool, a?: string}
     */
    private function cell(string $text, bool $bold = false, string $align = 'l'): array
    {
        $cell = ['t' => $text];
        if ($bold) {
            $cell['b'] = true;
        }
        if ($align !== 'l') {
            $cell['a'] = $align;
        }

        return $cell;
    }
}
