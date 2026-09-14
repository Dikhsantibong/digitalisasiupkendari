<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Renders one report body as several orientation segments (dompdf cannot mix
 * portrait & landscape pages in a single document) and merges them into one PDF
 * with FPDI, stamping a corporate footer + page numbers on every page.
 *
 * The same body HTML is rendered once per segment; each render hides the other
 * segments via injected CSS and sets its own @page orientation. This keeps the
 * cover/front matter portrait and the wide table page landscape, while the body
 * (and any saved edits) stays a single source.
 */
class OrientationPdfMerger
{
    /**
     * @param  list<array{show: string, orientation: string}>  $segments  in output order
     * @param  list<string>  $allSegments  every segment class (others are hidden per render)
     */
    public function render(string $stylesCss, string $bodyHtml, array $segments, array $allSegments, string $footerLabel): string
    {
        $pdfs = [];

        foreach ($segments as $seg) {
            $hidden = array_values(array_filter($allSegments, fn (string $c): bool => $c !== $seg['show']));
            $hideCss = $hidden === [] ? '' : '.'.implode(',.', $hidden).'{display:none!important;}';
            $orientation = $seg['orientation'] === 'landscape' ? 'landscape' : 'portrait';
            $pageSize = $orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';

            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
                .$stylesCss
                .' @page{size:'.$pageSize.';margin:14mm 12mm 18mm 12mm;} '.$hideCss
                .'</style></head><body>'.$bodyHtml.'</body></html>';

            $pdfs[] = Pdf::loadHTML($html)->setPaper('a4', $orientation)->output();
        }

        return $this->merge($pdfs, $footerLabel);
    }

    /**
     * @param  list<string>  $pdfContents
     */
    private function merge(array $pdfContents, string $footerLabel): string
    {
        $pdf = new MergedReportPdf($footerLabel);
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
