<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\Frame;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Renders one report body as several orientation segments (dompdf cannot mix
 * portrait & landscape pages in a single document) and merges them into one PDF
 * using PDFium (pypdfium2) or FPDI, stamping a corporate footer + page numbers on every page.
 */
class OrientationPdfMerger
{
    /**
     * @param  list<array{show: string, orientation: string}>  $segments  in output order
     * @param  list<string>  $allSegments  every segment class
     */
    public function render(string $stylesCss, string $bodyHtml, array $segments, array $allSegments, string $footerLabel): string
    {
        @ini_set('memory_limit', '512M');

        $pdfs = [];

        $cleanStyles = preg_replace('#</?style[^>]*>#i', '', $stylesCss);

        foreach ($segments as $seg) {
            $segHtml = $this->extractSegmentHtml($bodyHtml, $seg['show']);
            $orientation = $seg['orientation'] === 'landscape' ? 'landscape' : 'portrait';
            $pageSize = $orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';

            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
                .$cleanStyles
                .' @page{size:'.$pageSize.';margin:14mm 12mm 18mm 12mm;}'
                .'</style></head><body>'.$segHtml.'</body></html>';

            $pdfs[] = Pdf::loadHTML($html)->setPaper('a4', $orientation)->output();
        }

        return $this->merge($pdfs, $footerLabel);
    }

    /**
     * Renders a body made of `$sectionClass` blocks in document order, each on
     * A4 portrait — or landscape when the block also carries `$landscapeClass`.
     * Every section starts on a new page; consecutive sections of the same
     * orientation share one dompdf pass, and all passes are merged into one PDF.
     *
     * Table-of-contents links (`<a href="#section-id">`) get the page number the
     * linked element starts on. The first `$unnumberedPages` pages (e.g. the
     * cover) carry no footer and are not counted. A body without sections is
     * rendered whole in portrait.
     */
    public function renderSections(
        string $stylesCss,
        string $bodyHtml,
        string $sectionClass,
        string $landscapeClass,
        string $footerLabel,
        int $unnumberedPages = 0,
    ): string {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $styles = (string) preg_replace('#</?style[^>]*>#i', '', $stylesCss);

        $groups = $this->groupSections($bodyHtml, $sectionClass, $landscapeClass);
        if ($groups === []) {
            $groups = [['orientation' => 'portrait', 'html' => $bodyHtml]];
        }

        preg_match_all('/href=(["\'])[^"\']*#([A-Za-z][\w-]*)\1/', $bodyHtml, $matches);
        $linkedIds = array_values(array_unique($matches[2]));

        $rendered = [];
        $anchorPages = [];
        $offset = 0;

        foreach ($groups as $index => $group) {
            $rendered[$index] = $this->renderGroup($styles, $group['html'], $group['orientation'], $linkedIds);

            foreach ($rendered[$index]['anchors'] as $id => $page) {
                $anchorPages[$id] ??= $offset + $page - $unnumberedPages;
            }

            $offset += $rendered[$index]['pages'];
        }

        foreach ($groups as $index => $group) {
            $filled = $this->fillLinkedPages($group['html'], $anchorPages);

            if ($filled !== $group['html']) {
                $rendered[$index] = $this->renderGroup($styles, $filled, $group['orientation'], []);
            }
        }

        $totalPages = array_sum(array_column($rendered, 'pages'));

        return $this->merge(array_column($rendered, 'pdf'), $footerLabel, $unnumberedPages, $totalPages);
    }

    /**
     * Splits the body into runs of consecutive same-orientation sections. Only
     * outermost sections count, so a nested block never renders twice.
     *
     * @return list<array{orientation: string, html: string}>
     */
    private function groupSections(string $bodyHtml, string $sectionClass, string $landscapeClass): array
    {
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$bodyHtml.'</body></html>');
        libxml_clear_errors();

        $hasClass = fn (string $class): string => 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
        $nodes = (new \DOMXPath($doc))->query('//*['.$hasClass($sectionClass).'][not(ancestor::*['.$hasClass($sectionClass).'])]');

        $groups = [];
        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $classes = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];
            $orientation = in_array($landscapeClass, $classes, true) ? 'landscape' : 'portrait';
            $html = (string) $doc->saveHTML($node);

            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['orientation'] === $orientation) {
                $groups[$last]['html'] .= '<div style="page-break-after: always;"></div>'.$html;
            } else {
                $groups[] = ['orientation' => $orientation, 'html' => $html];
            }
        }

        return $groups;
    }

    /**
     * Renders one run of sections, recording the page (within this run) that
     * each of `$trackIds` starts on.
     *
     * @param  list<string>  $trackIds
     * @return array{pdf: string, pages: int, anchors: array<string, int>}
     */
    private function renderGroup(string $styles, string $html, string $orientation, array $trackIds): array
    {
        $document = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .$styles
            .' @page{size:A4 '.$orientation.';}'
            .'</style></head><body>'.$html.'</body></html>';

        $pdf = Pdf::loadHTML($document)->setPaper('a4', $orientation);
        $dompdf = $pdf->getDomPDF();

        $anchors = [];
        if ($trackIds !== []) {
            $wanted = array_flip($trackIds);
            $dompdf->setCallbacks([[
                'event' => 'begin_frame',
                'f' => function (Frame $frame, Canvas $canvas) use (&$anchors, $wanted): void {
                    $node = $frame->get_node();
                    if (! $node instanceof \DOMElement) {
                        return;
                    }

                    $id = $node->getAttribute('id');
                    if ($id !== '' && isset($wanted[$id]) && ! isset($anchors[$id])) {
                        $anchors[$id] = $canvas->get_page_number();
                    }
                },
            ]]);
        }

        $output = $pdf->output();

        return ['pdf' => $output, 'pages' => $dompdf->getCanvas()->get_page_count(), 'anchors' => $anchors];
    }

    /**
     * Writes each known page number as the text of the links pointing at it.
     *
     * @param  array<string, int>  $anchorPages
     */
    private function fillLinkedPages(string $html, array $anchorPages): string
    {
        if ($anchorPages === []) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<a\b[^>]*href=(["\'])[^"\']*#([A-Za-z][\w-]*)\2[^>]*>)(.*?)(<\/a>)/is',
            fn (array $m): string => isset($anchorPages[$m[3]]) ? $m[1].max(1, $anchorPages[$m[3]]).$m[5] : $m[0],
            $html,
        );
    }

    /**
     * Extracts only the specified segment HTML from the complete document body.
     */
    private function extractSegmentHtml(string $bodyHtml, string $segmentClass): string
    {
        if ($segmentClass === '') {
            return $bodyHtml;
        }

        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$bodyHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $node = $xpath->query('//div[contains(@class, "'.$segmentClass.'")]')->item(0);

        if ($node !== null) {
            return $doc->saveHTML($node) ?: $bodyHtml;
        }

        return $bodyHtml;
    }

    /**
     * @param  list<string>  $pdfContents
     */
    private function merge(array $pdfContents, string $footerLabel, int $unnumberedPages = 0, ?int $totalPages = null): string
    {
        if ($this->hasPdfium()) {
            $pdfiumMerged = $this->mergeWithPdfium($pdfContents);
            if ($pdfiumMerged !== null) {
                return $this->stampFooter($pdfiumMerged, $this->footerWriter($footerLabel, $unnumberedPages, $totalPages));
            }
        }

        return $this->mergeWithFpdi($pdfContents, $this->footerWriter($footerLabel, $unnumberedPages, $totalPages));
    }

    /**
     * The FPDI writer that stamps the footer; leading unnumbered pages need the
     * total up front, otherwise every page is numbered.
     */
    private function footerWriter(string $footerLabel, int $unnumberedPages, ?int $totalPages): MergedReportPdf
    {
        if ($unnumberedPages > 0 && $totalPages !== null) {
            return new MergedReportPdf($footerLabel, $unnumberedPages, max(0, $totalPages - $unnumberedPages));
        }

        return new MergedReportPdf($footerLabel);
    }

    /**
     * Checks if pypdfium2 (PDFium) CLI is available on the host system.
     */
    private function hasPdfium(): bool
    {
        static $available = null;
        if ($available === null) {
            @exec('pypdfium2 --version', $output, $returnCode);
            $available = ($returnCode === 0);
        }

        return $available;
    }

    /**
     * Merges PDF byte strings using PDFium CLI.
     *
     * @param  list<string>  $pdfContents
     */
    private function mergeWithPdfium(array $pdfContents): ?string
    {
        $tempFiles = [];
        $tempDir = sys_get_temp_dir();

        try {
            foreach ($pdfContents as $content) {
                if ($content === '') {
                    continue;
                }
                $file = tempnam($tempDir, 'pdf_seg_').'.pdf';
                file_put_contents($file, $content);
                $tempFiles[] = $file;
            }

            if ($tempFiles === []) {
                return null;
            }

            $outputFile = tempnam($tempDir, 'pdf_out_').'.pdf';
            $inputsArg = implode(' ', array_map('escapeshellarg', $tempFiles));
            $cmd = 'pypdfium2 arrange '.$inputsArg.' -o '.escapeshellarg($outputFile);

            @exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputFile) && filesize($outputFile) > 0) {
                $merged = (string) file_get_contents($outputFile);
                @unlink($outputFile);

                return $merged;
            }

            return null;
        } catch (\Throwable) {
            return null;
        } finally {
            foreach ($tempFiles as $f) {
                @unlink($f);
            }
        }
    }

    /**
     * Stamps corporate footer + page numbers across all pages.
     */
    private function stampFooter(string $pdfContent, MergedReportPdf $pdf): string
    {
        $pdf->SetAutoPageBreak(false);
        $pdf->AliasNbPages();

        $count = $pdf->setSourceFile(StreamReader::createByString($pdfContent));
        for ($i = 1; $i <= $count; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }

        return (string) $pdf->Output('S');
    }

    /**
     * Pure PHP fallback using FPDI to merge multiple PDFs and stamp footer.
     *
     * @param  list<string>  $pdfContents
     */
    private function mergeWithFpdi(array $pdfContents, MergedReportPdf $pdf): string
    {
        $pdf->SetAutoPageBreak(false);
        $pdf->AliasNbPages();

        foreach ($pdfContents as $content) {
            if ($content === '') {
                continue;
            }
            $count = $pdf->setSourceFile(StreamReader::createByString($content));
            for ($i = 1; $i <= $count; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }
        }

        return (string) $pdf->Output('S');
    }
}
