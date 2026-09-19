<?php

namespace App\Services\Reports;

use setasign\Fpdi\Fpdi;

/**
 * FPDI writer for merged reports: draws the corporate footer (source label +
 * "Halaman X / Y") on every page. FPDF is latin-1, so the label must avoid
 * non-latin1 characters (use a hyphen, not an em dash).
 *
 * The first `$unnumberedPages` pages (e.g. a cover) get no footer and are not
 * counted, so page 1 is the page right after them. `$numberedTotal` is then
 * required, since FPDF's `{nb}` alias can only print the raw page count.
 */
class MergedReportPdf extends Fpdi
{
    public function __construct(
        private readonly string $footerLabel,
        private readonly int $unnumberedPages = 0,
        private readonly ?int $numberedTotal = null,
    ) {
        parent::__construct();
    }

    public function Footer(): void
    {
        if ($this->PageNo() <= $this->unnumberedPages) {
            return;
        }

        $this->SetY(-12);
        $this->SetX($this->lMargin);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 6, $this->footerLabel, 0, 0, 'L');

        $total = $this->numberedTotal !== null ? (string) $this->numberedTotal : '{nb}';

        $this->SetY(-12);
        $this->SetX($this->lMargin);
        $this->Cell(0, 6, 'Halaman '.($this->PageNo() - $this->unnumberedPages).' / '.$total, 0, 0, 'R');
    }
}
