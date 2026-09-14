<?php

namespace App\Services\Reports;

use setasign\Fpdi\Fpdi;

/**
 * FPDI writer for merged reports: draws the corporate footer (source label +
 * "Halaman X / Y") on every page. FPDF is latin-1, so the label must avoid
 * non-latin1 characters (use a hyphen, not an em dash).
 */
class MergedReportPdf extends Fpdi
{
    public function __construct(private readonly string $footerLabel)
    {
        parent::__construct();
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetX($this->lMargin);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 6, $this->footerLabel, 0, 0, 'L');

        $this->SetY(-12);
        $this->SetX($this->lMargin);
        $this->Cell(0, 6, 'Halaman '.$this->PageNo().' / {nb}', 0, 0, 'R');
    }
}
