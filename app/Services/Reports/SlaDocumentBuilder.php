<?php

namespace App\Services\Reports;

use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Models\SlaDocument;
use App\Models\Unit;
use App\Services\Operasi\DocumentGridBuilder;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * The SLA as an editable document (user, 2026-11-19): the SLA of one divisi or
 * the Laporan SLA gabungan of one or every unit, shown in the document editor
 * (Teks / Excel / Pratinjau PDF) so its values can be edited and saved
 * ({@see SlaDocument}).
 *
 * The body is the SLA PDF view itself (reports/sla-pdf: kop, the criteria
 * tables and the rekapitulasi), one `.sla-doc-unit` block per unit, with its
 * styles scoped under `.sla-doc`. The cover of the gabungan stays automatic:
 * the PDF of a saved document prints, per unit block, the cover (gabungan)
 * then the edited block (A4 landscape) — the same pages as the template PDF.
 */
class SlaDocumentBuilder
{
    use EmbedsReportLogo;

    /** Bump when the template changes: documents saved on an older one render from the new template. */
    public const BODY_VERSION = 1;

    private const SCOPE = 'sla-doc';

    public function __construct(
        private readonly SlaReport $sla,
        private readonly SlaCover $cover,
        private readonly ScopedHtmlFragment $fragments,
        private readonly FragmentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
    ) {}

    /**
     * The SLA of each unit: one divisi, or every divisi for the gabungan.
     *
     * @param  iterable<Unit>  $units
     * @return list<array{unit: Unit, slas: list<array<string, mixed>>}>
     */
    public function entries(string $scope, iterable $units, int $month, int $year): array
    {
        $divisions = $scope === 'gabungan' ? array_keys(SlaCatalog::DIVISI) : [$scope];
        $entries = [];
        foreach ($units as $unit) {
            $entries[] = [
                'unit' => $unit,
                'slas' => array_map(fn (string $divisi): array => $this->sla->build($divisi, $unit, $month, $year), $divisions),
            ];
        }

        return $entries;
    }

    /**
     * The template body (one block per unit) and its scoped styles.
     *
     * @param  list<array{unit: Unit, slas: list<array<string, mixed>>}>  $entries
     * @return array{body: string, css: string}
     */
    public function template(array $entries, ?float $tagihan, bool $recap): array
    {
        $css = '';
        $blocks = [];
        foreach ($entries as $entry) {
            $html = View::make('reports.sla-pdf', ['slas' => $entry['slas'], 'tagihan' => $tagihan, 'recap' => $recap, 'printedAt' => Carbon::now()])->render();
            $fragment = $this->fragments->extract($html, self::SCOPE);
            $css = $fragment['css'];
            // The printed-on line is the PDF footer, not document content.
            $body = (string) preg_replace('#<div class="foot">.*?</div>#s', '', $fragment['body'], 1);
            $blocks[] = '<div class="sla-doc-unit" id="sla-unit-'.$entry['unit']->id.'" data-unit-id="'.$entry['unit']->id.'"><div class="'.self::SCOPE.'">'.$body.'</div></div>';
        }

        return ['body' => implode("\n", $blocks), 'css' => $css];
    }

    /**
     * The scoped SLA styles plus the editor's separation between units.
     */
    public function contentStyles(string $css): string
    {
        return $css."\n"
            .'.sla-doc-unit { background: #fff; }'."\n"
            .'.mce-content-body .sla-doc-unit + .sla-doc-unit { margin-top: 24px; padding-top: 12px; border-top: 2px dashed #cbd5e1; }';
    }

    /**
     * Kop shown above the spreadsheet editor (and printed with a grid document).
     */
    public function letterhead(string $title, string $unitLabel, string $periodLabel): string
    {
        return '<table style="width:100%; border-collapse:collapse;"><tr>'
            .'<td style="width:130px; vertical-align:middle;"><img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:46px;"></td>'
            .'<td style="vertical-align:middle; text-align:center;"><div class="har-org">SLA (SERVICE LEVEL AGREEMENT)<br>'.e(mb_strtoupper($title)).'<br><small>'.e(mb_strtoupper($unitLabel)).' — PERIODE '.e(mb_strtoupper($periodLabel)).'</small></div></td>'
            .'<td style="width:130px; text-align:right; vertical-align:middle;"><img src="/logo/mkp.jpg" alt="Mitra Karya Prima" style="height:46px;"></td>'
            .'</tr></table><hr class="har-hr">';
    }

    /**
     * The Excel edit mode: every table of every unit block, cell by cell.
     *
     * @return array<string, mixed>
     */
    public function grid(string $title, string $unitLabel, string $periodLabel, string $body): array
    {
        $parts = [];
        foreach ($this->unitBlocks($body) as $unitId => $block) {
            $parts[] = ['title' => Unit::query()->whereKey($unitId)->value('name') ?? 'SLA', 'body' => $block];
        }

        return $this->gridBuilder->build([
            'document' => ['title' => $title, 'grid_name' => 'SLA'],
            'report' => ['unit' => ['name' => $unitLabel], 'period' => ['label' => $periodLabel]],
            'parts' => $parts !== [] ? $parts : [['title' => $title, 'body' => $body]],
        ]);
    }

    /**
     * The PDF of a saved document: per unit block the cover (gabungan) then the
     * edited block on A4 landscape — or the grid (Excel mode) under the kop.
     */
    public function pdf(SlaDocument $document, string $css, string $title, string $unitLabel, int $month, int $year, bool $covers): string
    {
        $period = Indonesian::monthName($month).' '.$year;
        $foot = 'Dicetak '.Carbon::now()->timezone(config('app.timezone'))->format('d/m/Y H:i')." · {$title} · {$unitLabel} · Periode {$period}";

        if ($document->format === 'grid' && ! empty($document->content_grid)) {
            $html = $this->letterhead($title, $unitLabel, $period).$this->grids->gridToHtml($document->content_grid);

            return $this->landscape($this->embedAssets($html), '', $foot);
        }

        $blocks = $this->unitBlocks((string) $document->content_html);
        if ($blocks === []) {
            return $this->landscape($this->embedAssets((string) $document->content_html), $css, $foot);
        }

        $pdfs = [];
        foreach ($blocks as $unitId => $block) {
            $unit = Unit::query()->find($unitId);
            if ($covers && $unit !== null) {
                $pdfs[] = Pdf::loadView('reports.sla-cover', ['cover' => $this->cover->png($unit->name, Indonesian::monthName($month), $year)])->setPaper('a4', 'portrait')->output();
            }
            $pdfs[] = $this->landscape($this->embedAssets($block), $css, $foot);
        }

        return $this->merge($pdfs);
    }

    /**
     * The `.sla-doc-unit` blocks of a body, keyed by unit id (outer HTML).
     *
     * @return array<int, string>
     */
    public function unitBlocks(string $body): array
    {
        if (trim($body) === '' || ! str_contains($body, 'sla-doc-unit')) {
            return [];
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="sla-doc-root">'.$body.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $blocks = [];
        foreach ((new DOMXPath($dom))->query('//div[contains(concat(" ", normalize-space(@class), " "), " sla-doc-unit ")][not(ancestor::div[contains(concat(" ", normalize-space(@class), " "), " sla-doc-unit ")])]') ?: [] as $node) {
            $id = (int) $node->getAttribute('data-unit-id') ?: (int) preg_replace('/\D/', '', $node->getAttribute('id'));
            $blocks[$id] = ($blocks[$id] ?? '').$dom->saveHTML($node);
        }

        return $blocks;
    }

    private function landscape(string $body, string $css, string $foot): string
    {
        return Pdf::loadView('reports.sla-document-pdf', ['css' => $css, 'body' => $body, 'foot' => $foot])->setPaper('a4', 'landscape')->output();
    }

    /**
     * Pages of the PDFs one after another, each in its own orientation.
     *
     * @param  list<string>  $pdfs
     */
    private function merge(array $pdfs): string
    {
        if (count($pdfs) === 1) {
            return $pdfs[0];
        }

        $merged = new Fpdi;
        foreach ($pdfs as $content) {
            $pages = $merged->setSourceFile(StreamReader::createByString($content));
            for ($page = 1; $page <= $pages; $page++) {
                $template = $merged->importPage($page);
                $size = $merged->getTemplateSize($template);
                $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merged->useTemplate($template);
            }
        }

        return $merged->Output('', 'S');
    }
}
