<?php

namespace App\Services\Keuangan;

use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Models\CashCardReport;
use App\Models\KeuanganDocument;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Reports\FragmentGridBuilder;
use App\Services\Reports\ScopedHtmlFragment;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * The Laporan Administrasi Keuangan of a UL as an editable document (user,
 * 2026-11-20) — like the other reports: Teks / Excel / Pratinjau PDF. The
 * first page is the Laporan Realisasi Cash Card table (keuangan/cash-card-pdf),
 * then per row the Bukti Penerimaan Kas Kecil and its Lampiran
 * (keuangan/bukti-pdf), each view's styles scoped (`.keu-lap`, `.keu-bukti`).
 *
 * In the editor the photos are their public URLs; the PDF embeds them (and the
 * logos) again, so a saved document prints its photos too.
 */
class KeuanganDocumentBuilder
{
    use EmbedsReportLogo;

    /** Bump when the template changes: documents saved on an older one render from the new template. */
    public const BODY_VERSION = 1;

    public function __construct(
        private readonly CashCardDocument $documents,
        private readonly ScopedHtmlFragment $fragments,
        private readonly FragmentGridBuilder $gridBuilder,
        private readonly DocumentGridBuilder $grids,
    ) {}

    /**
     * The template body and its scoped styles.
     *
     * @return array{body: string, css: string, laporan: string}
     */
    public function template(CashCardReport $report): array
    {
        $laporan = $this->fragments->extract(View::make('keuangan.cash-card-pdf', $this->documents->laporan($report))->render(), 'keu-lap');
        $rows = $report->exists ? $this->documents->ledger($report)['rows'] : [];
        $bukti = $this->fragments->extract(View::make('keuangan.bukti-pdf', $this->documents->bukti($report, $rows, urls: true))->render(), 'keu-bukti');

        $body = '<div class="keu-section" id="keu-laporan"><div class="keu-lap">'.$laporan['body'].'</div></div>';
        if ($rows !== []) {
            $body .= "\n".'<div class="keu-section" id="keu-bukti" style="page-break-before: always;"><div class="keu-bukti">'.$bukti['body'].'</div></div>';
        }

        return ['body' => $body, 'css' => $laporan['css']."\n".$bukti['css'], 'laporan' => $laporan['body']];
    }

    /**
     * The scoped styles plus the editor's reading size and the separation of the parts.
     */
    public function contentStyles(string $css): string
    {
        // Editor only: the print type (8–10px, made for A4) is scaled up to read like the other reports.
        return $css."\n"
            .'.keu-section { background: #fff; }'."\n"
            .'.mce-content-body .keu-section + .keu-section { margin-top: 24px; padding-top: 12px; border-top: 2px dashed #cbd5e1; }'."\n"
            .'.keu-section .keu-lap, .keu-section .keu-bukti { font-size: 12.5px; line-height: 1.35; }'."\n"
            .'.keu-section .keu-lap table.cc th, .keu-section .keu-lap table.cc td { font-size: 12px; padding: 4px 6px; }'."\n"
            .'.keu-section .keu-lap .title { font-size: 16px; }'."\n"
            .'.keu-section .keu-lap .org, .keu-section .keu-bukti .org { font-size: 13px; }'."\n"
            .'.keu-section .keu-bukti .judul { font-size: 20px; }'."\n"
            .'.keu-section .keu-bukti .nomor { font-size: 28px; }'."\n"
            .'.mce-content-body .keu-bukti .page-break { margin: 18px 0; border-top: 1px dashed #cbd5e1; }';
    }

    public function letterhead(string $ulName, int $month, int $year): string
    {
        return '<table style="width:100%; border-collapse:collapse;"><tr>'
            .'<td style="width:130px; vertical-align:middle;"><img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" style="height:46px;"></td>'
            .'<td style="vertical-align:middle; text-align:center;"><div class="har-org">LAPORAN ADMINISTRASI KEUANGAN<br>LAPORAN REALISASI CASH CARD '.e(mb_strtoupper($ulName)).'<br><small>PERIODE '.e(mb_strtoupper(Indonesian::monthName($month))).' '.$year.'</small></div></td>'
            .'<td style="width:130px;"></td>'
            .'</tr></table><hr class="har-hr">';
    }

    /**
     * The Excel edit mode: the cash card table.
     *
     * @return array<string, mixed>
     */
    public function grid(string $ulName, int $month, int $year, string $laporanBody): array
    {
        return $this->gridBuilder->build([
            'document' => ['title' => 'Laporan Realisasi Cash Card', 'grid_name' => 'Cash Card'],
            'report' => ['unit' => ['name' => $ulName], 'period' => ['label' => Indonesian::monthName($month).' '.$year]],
            'parts' => [['title' => 'Laporan Realisasi Cash Card', 'body' => $laporanBody]],
        ]);
    }

    /**
     * The PDF: a saved document (Teks or Excel) or the template, A4 portrait.
     */
    public function pdf(?KeuanganDocument $document, array $template, string $ulName, int $month, int $year): string
    {
        if ($document !== null && $document->format === 'grid' && ! empty($document->content_grid)) {
            $body = $this->letterhead($ulName, $month, $year).$this->grids->gridToHtml($document->content_grid);
            $css = '';
        } else {
            $body = $document?->content_html ?? $template['body'];
            $css = $template['css'];
        }

        return Pdf::loadView('keuangan.document-pdf', ['css' => $css, 'body' => $this->embedPhotos($this->embedAssets($body))])
            ->setPaper('a4', 'portrait')->output();
    }

    /**
     * Stored photos (public-disk URLs) as data URIs for dompdf.
     */
    private function embedPhotos(string $html): string
    {
        return (string) preg_replace_callback(
            '#src=(["\'])[^"\']*?/storage/([^"\'?]+)[^"\']*\1#i',
            function (array $match): string {
                $uri = CashCardDocument::stored(rawurldecode($match[2]));

                return $uri === null ? $match[0] : 'src="'.$uri.'"';
            },
            $html,
        );
    }
}
