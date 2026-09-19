{{-- Operasi report styles — used by the document editor (content_style) and the
     PDF shell. Builds on the shared BA styles (letterhead/kop classes) and adds
     the report framework: cover, section headings, and one-section-per-page. --}}
@include('operasi.berita-acara.partials.ba-styles')
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
.op-h2 { font-weight: bold; font-size: 12px; margin: 12px 0 4px; }
.op-h3 { font-weight: bold; font-size: 11px; margin: 8px 0 3px; }
.op-p { text-align: justify; line-height: 1.5; margin: 4px 0; }
.op-muted { color: #555; font-size: 10px; }
.break-before { page-break-before: always; }
.op-toc { width: 100%; border-collapse: collapse; margin-top: 4px; }
.op-toc td { padding: 2px 4px; }
.op-toc td.n { width: 28px; font-weight: bold; }
.op-data { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 10px; }
.op-data th, .op-data td { border: 1px solid #000; padding: 2px 4px; }
.op-data th { background: #eee; text-align: center; }
.op-data td.r { text-align: right; white-space: nowrap; }
.op-data td.c { text-align: center; }
.op-wide { font-size: 8px; }
.op-wide th, .op-wide td { padding: 2px 3px; }
.op-data tr.total td { font-weight: bold; background: #f5f5f5; }
/* Corporate cover — redesigned matching PLN + MKP branding (media_1789321509916.png) */
.op-cover {
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
.op-cover-bg {
    position: absolute;
    left: 0;
    top: 0;
    width: 210mm;
    height: 297mm;
    z-index: 1;
}
.op-cover-content {
    position: relative;
    z-index: 10;
    padding-top: 32mm;
    text-align: center;
}
.op-logos-table {
    margin: 0 auto;
    border-collapse: collapse;
}
.op-logo-cell-left { vertical-align: middle; text-align: right; padding-right: 18px; }
.op-logo-divider-cell { vertical-align: middle; width: 3px; text-align: center; }
.op-logo-vdiv { width: 2px; height: 48px; background-color: #0b2545; }
.op-logo-cell-right { vertical-align: middle; text-align: left; padding-left: 18px; }
.op-logo-pln { height: 50px; }
.op-logo-mkp { height: 45px; }

.op-cover-title-wrap {
    margin-top: 28mm;
    text-align: center;
}
.op-cover-main-title {
    font-size: 26pt;
    font-weight: bold;
    color: #0b2545;
    text-transform: uppercase;
    letter-spacing: 2px;
    line-height: 1.3;
    margin: 0;
}
.op-cover-title-line {
    width: 220px;
    height: 2.5px;
    background-color: #0284c7;
    margin: 16px auto 0;
}
.op-cover-spec-box {
    margin: 26mm auto 0;
    width: 145mm;
    border: 2px solid #0284c7;
    border-radius: 16px;
    background: #ffffff;
    padding: 16px 22px;
    text-align: left;
}
.op-spec-table {
    width: 100%;
    border-collapse: collapse;
}
.op-spec-table td {
    padding: 4px 0;
    font-size: 10.5pt;
    font-weight: bold;
    color: #0b2545;
    text-transform: uppercase;
}
.op-spec-label { width: 55mm; }
.op-spec-colon { width: 6mm; text-align: center; }

.op-cover-pillars-badge {
    position: absolute;
    left: 14mm;
    bottom: 12mm;
    z-index: 10;
    background: #ffffff;
    border-radius: 6px;
    padding: 6px 12px;
    border: 1px solid #cbd5e1;
}
.op-pillars-table { border-collapse: collapse; }
.op-pillar-item { vertical-align: middle; padding: 0 6px; }
.op-pillar-sep { vertical-align: middle; color: #cbd5e1; font-size: 14pt; padding: 0 2px; }
.op-p-icon { width: 18px; height: 18px; vertical-align: middle; display: inline-block; }
.op-p-text { vertical-align: middle; display: inline-block; margin-left: 4px; color: #0b2545; line-height: 1.1; }
.op-p-text strong { font-size: 7pt; display: block; font-weight: bold; }
.op-p-text small { font-size: 5.5pt; color: #0b2545; }
/* Editor-only cover fix. The cover above uses print bleed (negative margins)
   and page-break rules so it fills a full A4 sheet in dompdf. In the flowing
   on-screen editor those same rules make the cover collapse and the next
   section overlaps it. These overrides are scoped to the editor body
   (`.mce-content-body`); dompdf's PDF output has no such class, so the printed
   cover is left exactly as-is. */
.mce-content-body .op-cover {
    /* Drop the print bleed margins and render the cover as a framed A4 page. */
    margin: 0 auto 24px auto;
    width: 210mm;
    height: 297mm;
    max-width: 100%;
    border: 1px solid #cbd5e1;
    box-shadow: 0 2px 14px rgba(0, 0, 0, 0.15);
    background: #ffffff;
}
.mce-content-body .op-cover-bg {
    /* Keep the artwork clamped to the framed page in the editor. */
    max-width: 100%;
}
/* Give each subsequent "page" a clear gap so sections don't butt together on
   screen (page-break rules are inert in the editor). */
.mce-content-body .break-before {
    margin-top: 24px;
    padding-top: 8px;
    border-top: 1px dashed #cbd5e1;
}

/* --- Daftar Isi layout (one .op-section per point, see document-body) --- */
.page-break { page-break-after: always; break-after: page; clear: both; }
.mce-content-body .page-break { margin: 18px 0; border-top: 1px dashed #cbd5e1; }

/* Official kop 3 columns (PLN NP · text · MKP) */
.op-kop { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 12px; }
.op-kop td { color: #000; }
.op-kop-logo { width: 135px; text-align: center; vertical-align: middle; padding: 6px 8px; }
.op-kop-logo-left { border-right: 1.5px solid #000; }
.op-kop-logo-right { border-left: 1.5px solid #000; }
.op-kop-cell { text-align: center; vertical-align: middle; padding: 5px 8px; font-weight: bold; font-size: 10pt; border-bottom: 1px solid #000; letter-spacing: 0.3px; }
.op-kop-cell.op-kop-title { font-size: 10.5pt; letter-spacing: 0.5px; border-bottom: none; }

.op-toc-table { width: 100%; border-collapse: collapse; margin: 0; }
.op-toc-table td { padding: 2px 4px; vertical-align: middle; font-size: 9pt; line-height: 1.2; border-bottom: 1px dotted #94a3b8; }
.op-toc-table td.n { width: 34px; font-weight: bold; }
.op-toc-table td.pg { width: 60px; text-align: right; white-space: nowrap; font-weight: bold; }
.op-toc-table td.pg a, .op-data td a { color: #000; text-decoration: none; }
.op-toc-table tr.op-toc-head td { font-weight: bold; background-color: #f1f5f9; border-top: 1px solid #0b2545; border-bottom: 1px solid #0b2545; }

.op-part-title { font-weight: bold; font-size: 11pt; margin: 8px 0 4px; color: #0b2545; }
.op-sub-title { font-weight: bold; font-size: 10.5pt; margin: 10px 0 6px; color: #0b2545; }
.op-period { font-weight: bold; font-size: 10pt; margin-bottom: 6px; }
.op-data tr.op-group-row td { font-weight: bold; background: #e2e8f0; }
.op-layout { width: 100%; border-collapse: collapse; margin-top: 10px; }
.op-layout > tr > td, .op-layout td { vertical-align: top; }
.op-legend { font-size: 8px; color: #475569; margin-top: 4px; }

/* Day / month matrices on landscape pages */
.op-grid { font-size: 7px; }
.op-grid th, .op-grid td { padding: 2px 1px; }
.op-grid td.op-name { text-align: left; padding-left: 3px; }
.op-done { background: #bbf7d0; font-weight: bold; }
.op-plan { background: #fef08a; }
.op-current { background: #fde68a; }
.op-day-off { background: #fecaca; }

/* Red line shown in place of a table whose data has not been input yet */
.op-no-data { margin: 10px 0 16px 0; }
.op-red-line { width: 100%; height: 3px; background-color: #dc2626; margin: 0 0 4px 0; }
.op-no-data-text { color: #b91c1c; font-size: 8.5pt; font-style: italic; }

/* --- Lembar Pengesahan & Resume Statistik charts --- */
.op-pengesahan { margin: 24px 36px 0 36px; font-size: 11pt; line-height: 1.5; }
.op-pengesahan p { margin: 0 0 14px 0; }
.op-pengesahan-date { text-align: right; margin-top: 40px !important; }
.op-pengesahan-sign { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11pt; }
.op-pengesahan-sign td { width: 50%; text-align: center; vertical-align: top; padding-bottom: 18px; }
.op-sign-space { height: 70px; }
.op-sign-space img { max-height: 66px; max-width: 150px; }
.op-chart { text-align: center; margin-top: 8px; }
.op-chart img { width: 100%; max-width: 430px; }
.op-chart.op-chart-pie img { max-width: 330px; }
