<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders a report Blade to a paginated A4 PDF and stamps every page with the
 * corporate footer (source label + "Halaman X / Y") using dompdf's canvas
 * page_text API.
 *
 * The footer is drawn after layout — NOT via a CSS `position: fixed` element,
 * which dompdf mis-paginates on multi-page documents (it duplicates pages).
 */
trait RendersReportPdf
{
    private const FOOTER_LABEL = 'Dicetak oleh Sistem Manajemen Terintegrasi Pembangkitan — PT PLN Nusantara Power UP Kendari';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function streamReportPdf(Request $request, string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $grey = [0.4, 0.4, 0.4];

        // Drawn on every page in the bottom margin; {PAGE_NUM}/{PAGE_COUNT} are
        // substituted by dompdf per page.
        $canvas->page_text(34, $height - 26, self::FOOTER_LABEL, $font, 7, $grey);
        $canvas->page_text($width - 150, $height - 26, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 8, $grey);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
