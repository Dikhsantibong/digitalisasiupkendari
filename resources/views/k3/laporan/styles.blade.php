/* Shared K3 report styles — used by the editor and the PDF shell. */
* { font-family: 'DejaVu Sans', Arial, sans-serif; }
body { font-size: 11px; color: #000; }
/* Paged media: A4 portrait with room for the fixed footer. */
@page { size: A4 portrait; margin: 16mm 12mm 22mm 12mm; }
.report-footer { position: fixed; left: 0; right: 0; bottom: -16mm; height: 12mm; font-size: 8px; color: #555; border-top: 0.5pt solid #999; padding-top: 3px; }
.report-footer .rf-left { float: left; }
.report-footer .rf-right { float: right; }
.report-footer .rf-page:after { content: counter(page) " / " counter(pages); }
.toc-item { width: 100%; border-collapse: collapse; margin: 0; }
.toc-item td { padding: 3px 0; vertical-align: bottom; font-size: 11px; }
.toc-item td.n { width: 26px; font-weight: bold; }
.toc-item td.dots { border-bottom: 1px dotted #666; width: 100%; }
.toc-item td.pg { text-align: right; white-space: nowrap; padding-left: 8px; font-weight: bold; }
.toc-item td.pg a { color: #000; text-decoration: none; }
.toc-item td.pg a:after { content: target-counter(attr(href), page); }

.toc-table { width: 100%; border-collapse: collapse; margin: 0; }
.toc-table td { padding: 0.5px 4px; vertical-align: middle; font-size: 7.5pt; line-height: 1.1; border-bottom: 1px dotted #94a3b8; }
.toc-table td.n { width: 34px; font-weight: bold; }
.toc-table td.pg { width: 60px; text-align: right; white-space: nowrap; font-weight: bold; }
.toc-table td.pg a { color: #000; text-decoration: none; }
.toc-table tr.toc-head td { font-weight: bold; font-size: 8.5pt; background-color: #f1f5f9; border-top: 1px solid #0b2545; border-bottom: 1px solid #0b2545; }
.k3-org { text-align: center; font-weight: bold; line-height: 1.35; font-size: 12px; }
.k3-org small { font-weight: normal; }
.k3-meta { border: 1px solid #000; border-collapse: collapse; width: 100%; font-size: 10px; }
.k3-meta td { border: 1px solid #000; padding: 3px 6px; }
.k3-title { text-align: center; font-weight: bold; font-size: 12px; margin-top: 8px; }
.k3-hr { border: none; border-top: 1px solid #000; margin-top: 6px; }
.k3-h2 { font-weight: bold; font-size: 11px; margin: 12px 0 4px; }
.k3-h3 { font-weight: bold; font-size: 11px; margin: 8px 0 3px; }
.k3-p { text-align: justify; line-height: 1.5; margin: 4px 0; }
.k3-muted { color: #555; font-size: 10px; }
.break-before { page-break-before: always; }
.k3-toc { width: 100%; border-collapse: collapse; margin-top: 4px; }
.k3-toc td { padding: 2px 4px; }
.k3-toc td.n { width: 28px; font-weight: bold; }
.k3-data { width: 100%; border-collapse: collapse; margin-top: 4px; }
.k3-data th, .k3-data td { border: 1px solid #000; padding: 2px 4px; }
.k3-data th { background: #eee; text-align: center; }
.k3-data td.c { text-align: center; }
.k3-note { color: #555; }
.k3-nihil { font-weight: bold; }
.k3-fig { display: inline-block; width: 48%; vertical-align: top; border: 1px solid #000; padding: 4px; margin: 0 2px 6px 0; }
.page-break { page-break-after: always; break-after: page; clear: both; }
.avoid-break { page-break-inside: avoid; break-inside: avoid; }

/* Standard Data Tables */
.report-table { width: 100%; border-collapse: collapse; margin-top: 6px; margin-bottom: 12px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.report-table th, .report-table td { border: 1px solid #64748b; padding: 4px 6px; vertical-align: middle; }
.report-table th { background-color: #f1f5f9; color: #0f172a; font-weight: bold; font-size: 8.5px; text-align: center; }
.report-table td { font-size: 8px; color: #1e293b; }
.report-table tbody tr:nth-child(even) { background-color: #f8fafc; }

/* Wide Landscape Tables */
.wide-table { width: 100%; border-collapse: collapse; margin-top: 6px; margin-bottom: 10px; font-family: 'DejaVu Sans', Arial, sans-serif; }
.wide-table th, .wide-table td { border: 1px solid #475569; padding: 3px 4px; vertical-align: middle; }
.wide-table th { background-color: #e2e8f0; color: #0f172a; font-weight: bold; font-size: 7.5px; text-align: center; line-height: 1.2; }
.wide-table td { font-size: 7.5px; color: #1e293b; }
.wide-table tbody tr:nth-child(even) { background-color: #f8fafc; }

/* Time Frame Day Cells */
.day-col { width: 17px; text-align: center; font-size: 6.5px; padding: 2px 0 !important; }
.mark-r { color: #1d4ed8; font-weight: bold; }
.mark-rl { color: #15803d; font-weight: bold; }

/* Pekerjaan Rutin Matrix Cells */
.th-day-red { color: #dc2626 !important; font-weight: bold; }
.td-red { background-color: #fca5a5 !important; }
.td-green { background-color: #86efac !important; color: #000 !important; font-weight: bold; }
.td-yellow { background-color: #fef08a !important; color: #000 !important; font-weight: bold; }
.name-cell { text-align: left !important; padding-left: 4px !important; font-weight: 600; }
.status-cell { font-weight: bold; background-color: #f1f5f9; text-align: center; }
.stat-cell { font-weight: bold; text-align: center; }
.paraf-cell { font-size: 6.5px; color: #4b5563; text-align: center; }
.legend-box { margin-top: 6px; font-size: 7.5px; line-height: 1.35; color: #111; }

/* Status Badges */
.badge { display: inline-block; padding: 2px 5px; border-radius: 3px; font-size: 7.5px; font-weight: bold; text-align: center; }
.badge-success { background-color: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.badge-warning { background-color: #fef9c3; color: #a16207; border: 1px solid #fde047; }
.badge-danger { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.badge-neutral { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

/* Attachments Gallery */
.photo-grid-table { width: 100%; border-collapse: collapse; border: none !important; margin-top: 10px; }
.photo-grid-table td { border: none !important; width: 50%; padding: 8px; vertical-align: top; }
.photo-card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px; background: #f8fafc; text-align: center; }
.photo-card img { width: 100%; max-height: 220px; object-fit: cover; border-radius: 4px; }
.photo-caption { margin-top: 6px; font-size: 8.5px; font-weight: bold; color: #1e293b; }
.photo-meta { font-size: 7.5px; color: #64748b; }

/* Utilities */
.text-center { text-align: center !important; }
.text-right { text-align: right !important; }
.text-left { text-align: left !important; }
.font-bold { font-weight: bold !important; }
.text-muted { color: #64748b !important; }

/* Official Kop 3 columns (PLN NP - Text - K3) */
.k3-official-kop {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    margin-bottom: 14px;
    font-family: 'DejaVu Sans', Arial, sans-serif;
}
.k3-official-kop td {
    color: #000;
}
.kop-logo-left {
    width: 135px;
    text-align: center;
    vertical-align: middle;
    padding: 6px 8px;
    border-right: 1.5px solid #000;
}
.kop-logo-right {
    width: 135px;
    text-align: center;
    vertical-align: middle;
    padding: 6px 8px;
    border-left: 1.5px solid #000;
}
.kop-center-cell {
    text-align: center;
    vertical-align: middle;
    padding: 5px 8px;
    font-weight: bold;
    font-size: 10pt;
    border-bottom: 1px solid #000;
    letter-spacing: 0.3px;
    color: #000;
}
.kop-center-cell.kop-section-title {
    font-size: 10.5pt;
    letter-spacing: 0.5px;
    border-bottom: none;
}

/* Red line & pending table indicator */
.k3-pending-box {
    margin: 10px 0 16px 0;
    padding: 8px 12px;
    background-color: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 4px solid #dc2626;
    border-radius: 4px;
}
.k3-red-line {
    width: 100%;
    height: 3px;
    background-color: #dc2626;
    margin: 6px 0 8px 0;
}
.k3-pending-title {
    color: #991b1b;
    font-weight: bold;
    font-size: 10pt;
    margin-bottom: 3px;
}
.k3-pending-desc {
    color: #7f1d1d;
    font-size: 9pt;
    line-height: 1.35;
}
.k3-pending-badge {
    display: inline-block;
    background-color: #dc2626;
    color: #ffffff;
    font-size: 7.5pt;
    font-weight: bold;
    padding: 1px 6px;
    border-radius: 3px;
    margin-right: 6px;
    vertical-align: middle;
}

/* Resume Statistik Table */
.k3-resume-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9.5pt;
}
.k3-resume-table th, .k3-resume-table td {
    border: 1px solid #000;
    padding: 4px 6px;
}
.k3-resume-table th {
    background-color: #93c5fd;
    color: #000;
    text-align: center;
    font-weight: bold;
    font-size: 9.5pt;
}
.k3-resume-table td.c {
    text-align: center;
}
.k3-resume-table .group-row {
    background-color: #f1f5f9;
    font-weight: bold;
}

/* Pengesahan styling */
.k3-pengesahan-body {
    font-size: 10pt;
    line-height: 1.6;
    color: #000;
    font-family: 'DejaVu Sans', Arial, sans-serif;
    margin-top: 24px;
    padding: 0 4px;
}

@include('k3.input.partials.table-styles')

/* Report part heading (V., VI., VII.) */
.k3-part-title {
    font-weight: bold;
    font-size: 11pt;
    margin: 10px 0 4px 0;
    color: #0b2545;
}

/* Red line shown in place of a table whose data has not been input yet */
.k3-no-data { margin: 10px 0 16px 0; }
.k3-no-data .k3-red-line { height: 3px; margin: 0 0 4px 0; }
.k3-no-data-text { color: #b91c1c; font-size: 8.5pt; font-style: italic; }

/* Sub-section headings */
.k3-sub-title {
    font-weight: bold;
    font-size: 10.5pt;
    margin: 18px 0 6px 0;
    color: #0b2545;
}

/* Corporate cover — redesigned matching PLN + MKP branding */
.k3-cover {
    margin: -16mm -12mm -22mm -12mm;
    padding: 0;
    width: 210mm;
    min-height: 297mm;
    height: 297mm;
    position: relative;
    overflow: hidden;
    background: #ffffff;
    box-sizing: border-box;
}
.k3-cover-bg {
    position: absolute;
    left: 0;
    top: 0;
    width: 210mm;
    height: 297mm;
    z-index: 1;
}
.k3-cover-content {
    position: relative;
    z-index: 10;
    padding-top: 32mm;
    text-align: center;
}
.k3-logos-table {
    margin: 0 auto;
    border-collapse: collapse;
}
.k3-logo-cell-left { vertical-align: middle; text-align: right; padding-right: 18px; }
.k3-logo-divider-cell { vertical-align: middle; width: 3px; text-align: center; }
.k3-logo-vdiv { width: 2px; height: 48px; background-color: #0b2545; }
.k3-logo-cell-right { vertical-align: middle; text-align: left; padding-left: 18px; }
.k3-logo-pln { height: 50px; }
.k3-logo-mkp { height: 45px; }

.k3-cover-title-wrap {
    margin-top: 28mm;
    text-align: center;
}
.k3-cover-main-title {
    font-size: 26pt;
    font-weight: bold;
    color: #0b2545;
    text-transform: uppercase;
    letter-spacing: 2px;
    line-height: 1.3;
    margin: 0;
}
.k3-cover-title-line {
    width: 220px;
    height: 2.5px;
    background-color: #0284c7;
    margin: 16px auto 0;
}
.k3-cover-spec-box {
    margin: 26mm auto 0;
    width: 145mm;
    border: 2px solid #0284c7;
    border-radius: 16px;
    background: #ffffff;
    padding: 16px 22px;
    text-align: left;
}
.k3-spec-table {
    width: 100%;
    border-collapse: collapse;
}
.k3-spec-table td {
    padding: 4px 0;
    font-size: 10.5pt;
    font-weight: bold;
    color: #0b2545;
    text-transform: uppercase;
}
.k3-spec-label { width: 55mm; }
.k3-spec-colon { width: 6mm; text-align: center; }

.k3-cover-pillars-badge {
    position: absolute;
    left: 14mm;
    bottom: 12mm;
    z-index: 10;
    background: #ffffff;
    border-radius: 6px;
    padding: 6px 12px;
    border: 1px solid #cbd5e1;
}
.k3-pillars-table { border-collapse: collapse; }
.k3-pillar-item { vertical-align: middle; padding: 0 6px; }
.k3-pillar-sep { vertical-align: middle; color: #cbd5e1; font-size: 14pt; padding: 0 2px; }
.k3-p-icon { width: 18px; height: 18px; vertical-align: middle; display: inline-block; }
.k3-p-text { vertical-align: middle; display: inline-block; margin-left: 4px; color: #0b2545; line-height: 1.1; }
.k3-p-text strong { font-size: 7pt; display: block; font-weight: bold; }
.k3-p-text small { font-size: 5.5pt; color: #0b2545; }

/* --- Editor-only cover fix (TinyMCE) --------------------------------------
   The cover is authored for paged PDF output: negative page-bleed margins and
   page-break rules fill a full A4 sheet in dompdf. In the flowing on-screen
   editor those collapse the cover and the next section overlaps it. These
   overrides are scoped to the editor body (`.mce-content-body`); dompdf's PDF
   output has no such class, so the printed cover is left exactly as-is. */
.mce-content-body .k3-cover {
    margin: 0 auto 24px auto;
    width: 210mm;
    height: 297mm;
    max-width: 100%;
    border: 1px solid #cbd5e1;
    box-shadow: 0 2px 14px rgba(0, 0, 0, 0.15);
    background: #ffffff;
}
.mce-content-body .k3-cover-bg {
    max-width: 100%;
}
.mce-content-body .break-before {
    margin-top: 24px;
    padding-top: 8px;
    border-top: 1px dashed #cbd5e1;
}
