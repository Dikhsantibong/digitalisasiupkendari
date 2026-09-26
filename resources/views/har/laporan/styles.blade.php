/* Shared HAR report styles — used by the editor (content_style) and the PDF
   shell, so the edited HTML renders identically in both. */
* { font-family: 'DejaVu Sans', Arial, sans-serif; }
body { font-size: 11px; color: #000; }
/* Paged media: A4 portrait with room for the fixed footer. */
@page { size: A4 portrait; margin: 16mm 12mm 22mm 12mm; }
.report-footer { position: fixed; left: 0; right: 0; bottom: -16mm; height: 12mm; font-size: 8px; color: #555; border-top: 0.5pt solid #999; padding-top: 3px; }
.report-footer .rf-left { float: left; }
.report-footer .rf-right { float: right; }
.report-footer .rf-page:after { content: counter(page) " / " counter(pages); }
/* Table of contents with dotted leaders + real page numbers (target-counter). */
.toc-item { width: 100%; border-collapse: collapse; margin: 0; }
.toc-item td { padding: 3px 0; vertical-align: bottom; font-size: 11px; }
.toc-item td.n { width: 26px; font-weight: bold; }
.toc-item td.dots { border-bottom: 1px dotted #666; width: 100%; }
.toc-item td.pg { text-align: right; white-space: nowrap; padding-left: 8px; font-weight: bold; }
.toc-item td.pg a { color: #000; text-decoration: none; }
.toc-item td.pg a:after { content: target-counter(attr(href), page); }
.har-wo { font-size: 8px; }
.har-wo th, .har-wo td { padding: 2px 3px; }
.har-org { text-align: center; font-weight: bold; line-height: 1.35; font-size: 12px; }
.har-org small { font-weight: normal; }
.har-meta { border: 1px solid #000; border-collapse: collapse; width: 100%; font-size: 10px; }
.har-meta td { border: 1px solid #000; padding: 3px 6px; }
.har-title { text-align: center; font-weight: bold; font-size: 12px; margin-top: 8px; }
.har-hr { border: none; border-top: 1px solid #000; margin-top: 6px; }
.har-h2 { font-weight: bold; font-size: 11px; margin: 12px 0 4px; }
.har-data { width: 100%; border-collapse: collapse; margin-top: 4px; }
.har-data th, .har-data td { border: 1px solid #000; padding: 2px 4px; }
.har-data th { background: #eee; text-align: center; }
.har-data td.r { text-align: right; white-space: nowrap; }
.har-data td.c { text-align: center; }
.har-note { color: #555; }
.har-h3 { font-weight: bold; font-size: 11px; margin: 8px 0 3px; }
.har-p { text-align: justify; line-height: 1.5; margin: 4px 0; }
.har-muted { color: #555; font-size: 10px; }
.break-before { page-break-before: always; }
/* Sections are split into pages by OrientationPdfMerger::renderSections, which
   already breaks between them — the cover must not add a break of its own. */
.har-section.har-cover { page-break-after: auto; }
.har-toc-group td { background: #f8fafc; }
/* Corporate cover — redesigned matching PLN + MKP branding */
.har-cover {
    page-break-after: always;
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
.har-cover-bg {
    position: absolute;
    left: 0;
    top: 0;
    width: 210mm;
    height: 297mm;
    z-index: 1;
}
.har-cover-content {
    position: relative;
    z-index: 10;
    padding-top: 32mm;
    text-align: center;
}
.har-logos-table {
    margin: 0 auto;
    border-collapse: collapse;
}
.har-logo-cell-left { vertical-align: middle; text-align: right; padding-right: 18px; }
.har-logo-divider-cell { vertical-align: middle; width: 3px; text-align: center; }
.har-logo-vdiv { width: 2px; height: 48px; background-color: #0b2545; }
.har-logo-cell-right { vertical-align: middle; text-align: left; padding-left: 18px; }
.har-logo-pln { height: 50px; }
.har-logo-mkp { height: 45px; }
.har-logo-k3 { height: 48px; }

.har-cover-title-wrap {
    margin-top: 28mm;
    text-align: center;
}
.har-cover-main-title {
    font-size: 26pt;
    font-weight: bold;
    color: #0b2545;
    text-transform: uppercase;
    letter-spacing: 2px;
    line-height: 1.3;
    margin: 0;
}
.har-cover-title-line {
    width: 220px;
    height: 2.5px;
    background-color: #0284c7;
    margin: 16px auto 0;
}
.har-cover-spec-box {
    margin: 26mm auto 0;
    width: 145mm;
    border: 2px solid #0284c7;
    border-radius: 16px;
    background: #ffffff;
    padding: 16px 22px;
    text-align: left;
}
.har-spec-table {
    width: 100%;
    border-collapse: collapse;
}
.har-spec-table td {
    padding: 4px 0;
    font-size: 10.5pt;
    font-weight: bold;
    color: #0b2545;
    text-transform: uppercase;
}
.har-spec-label { width: 55mm; }
.har-spec-colon { width: 6mm; text-align: center; }

.har-cover-pillars-badge {
    position: absolute;
    left: 14mm;
    bottom: 12mm;
    z-index: 10;
    background: #ffffff;
    border-radius: 6px;
    padding: 6px 12px;
    border: 1px solid #cbd5e1;
}
.har-pillars-table { border-collapse: collapse; }
.har-pillar-item { vertical-align: middle; padding: 0 6px; }
.har-pillar-sep { vertical-align: middle; color: #cbd5e1; font-size: 14pt; padding: 0 2px; }
.har-p-icon { width: 18px; height: 18px; vertical-align: middle; display: inline-block; }
.har-p-text { vertical-align: middle; display: inline-block; margin-left: 4px; color: #0b2545; line-height: 1.1; }
.har-p-text strong { font-size: 7pt; display: block; font-weight: bold; }
.har-p-text small { font-size: 5.5pt; color: #0b2545; }
.har-toc { width: 100%; border-collapse: collapse; margin-top: 4px; }
.har-toc td { padding: 2px 4px; }
.har-toc td.n { width: 28px; font-weight: bold; }
.har-figs { margin-top: 6px; }
.har-fig { display: inline-block; width: 48%; vertical-align: top; border: 1px solid #000; padding: 4px; margin: 0 2px 6px 0; }
.har-fig img { max-width: 100%; max-height: 220px; }
.har-fig figcaption { font-size: 10px; }

/* --- Editor-only cover fix (TinyMCE) --------------------------------------
   The cover is authored for paged PDF output: it uses negative page-bleed
   margins and page-break rules so it fills a full A4 sheet in dompdf. In the
   flowing on-screen editor those same rules make the cover collapse and the
   next section overlaps it. These overrides are scoped to the editor body
   (`.mce-content-body`); dompdf's PDF output has no such class, so the printed
   cover is left exactly as-is. */
.mce-content-body .har-cover {
    /* Drop the print bleed margins and render the cover as a framed A4 page. */
    margin: 0 auto 24px auto;
    width: 210mm;
    height: 297mm;
    max-width: 100%;
    border: 1px solid #cbd5e1;
    box-shadow: 0 2px 14px rgba(0, 0, 0, 0.15);
    background: #ffffff;
}
.mce-content-body .har-cover-bg {
    /* Keep the artwork clamped to the framed page in the editor. */
    max-width: 100%;
}
/* Give each subsequent "page" a clear gap so sections don't butt together on
   screen (page-break rules are inert in the editor). */
.mce-content-body .break-before,
.mce-content-body .har-section + .har-section {
    margin-top: 24px;
    padding-top: 8px;
    border-top: 1px dashed #cbd5e1;
}

/* Schedule tables styles (Jadwal Pemeliharaan) */
.header-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 6px; }
.header-table td { vertical-align: middle; border: 1px solid #000; }
.logo-box { width: 130px; text-align: center; padding: 3px; }
.logo-box img { max-height: 42px; max-width: 120px; }
.title-box { text-align: center; padding: 3px 6px; }
.title-box h1 { margin: 0; font-size: 10px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
.title-box h2 { margin: 1.5px 0 0 0; font-size: 9.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
.title-box h3 { margin: 1.5px 0 0 0; font-size: 8.5px; font-weight: bold; line-height: 1.25; text-transform: uppercase; }
.title-row { font-weight: bold; font-size: 9px; line-height: 1.3; }
.meta-box { width: 150px; padding: 0; font-size: 7.5px; }
.meta-table { width: 100%; border-collapse: collapse; }
.meta-table td { border: none; border-bottom: 1px solid #000; padding: 2.5px 4px; }
.meta-table tr:last-child td { border-bottom: none; }
.data-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; font-size: 7.5px; }
.data-table th, .data-table td { border: 1px solid #000; padding: 2.5px 1px; text-align: center; vertical-align: middle; }
.data-table thead th { background-color: #ffffff; font-weight: bold; color: #000; }
.th-day-red, .text-red { color: #dc2626 !important; font-weight: bold; }
.td-red, .th-red { background-color: #ff0000 !important; color: #ffffff !important; }
.activity-name { text-align: left !important; padding-left: 5px !important; font-weight: normal; }
.keterangan-cell { text-align: left !important; padding-left: 4px !important; font-size: 7px; }
.stat-cell { font-weight: bold; text-align: center; }
.legend-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; font-size: 7.5px; }
.legend-table td { padding: 1px 3px; vertical-align: middle; }
.color-box { display: inline-block; width: 18px; height: 10px; border: 1px solid #000; vertical-align: middle; }
.info-bar { margin-bottom: 4px; font-size: 8px; }
.cell-yellow { background-color: #ffff00 !important; color: #000000 !important; font-weight: bold; }
.cell-green { background-color: #92d050 !important; color: #000000 !important; font-weight: bold; }
.cell-bold { font-weight: bold; color: #000; }
.machine-name-cell { text-align: left; padding-left: 3px; font-weight: bold; }
.machine-sub { font-size: 7px; }
.category-row td { background-color: #f1f5f9; font-weight: bold; text-align: center; padding: 2.5px 1px; }
.category-name { text-align: left !important; padding-left: 6px !important; }
.person-name { text-align: left !important; padding-left: 4px !important; }
.phone-cell { font-size: 7.5px; text-align: left !important; padding-left: 4px !important; }
.cell-piket-red { background-color: #ef4444 !important; color: #ffffff !important; font-weight: bold; }
.cell-piket-normal { font-weight: bold; color: #000000; }
.operator-name { text-align: left !important; padding-left: 4px !important; font-weight: bold; }
.cell-piket { background-color: #00b0f0 !important; color: #000000 !important; font-weight: bold; }
.recap-cell { font-weight: bold; text-align: center; }
.footer-table { width: 100%; margin-top: 6px; border-collapse: collapse; font-size: 7.5px; }
.footer-table td { padding: 1px 2px; vertical-align: middle; }
.uraian-cell { text-align: left !important; padding-left: 5px !important; font-weight: bold; }
.category-cell { font-weight: bold; background-color: #f8fafc; }
.th-orange { background-color: #fce4d6 !important; color: #000 !important; font-weight: bold; }
.ik-title { text-align: left !important; padding-left: 4px !important; }
.pic-name { text-align: center; }
.month-cell { padding: 1px 0 !important; font-size: 7px; }
.month-cell-both { color: #16a34a; font-weight: bold; }
.month-cell-rencana { color: #2563eb; font-weight: bold; }
.month-cell-realisasi { color: #16a34a; font-weight: bold; }
.total-row td { font-weight: bold; background-color: #f9fafb; }
.recap-table { margin-top: 8px; border-collapse: collapse; border: 1px solid #000; font-size: 7.5px; }
.recap-table th, .recap-table td { border: 1px solid #000; padding: 2.5px 4px; text-align: center; vertical-align: middle; }

/* -------------------------------------------------------------
   FORMULIR PEMELIHARAAN (13 FORMS EMBEDDED STYLES)
------------------------------------------------------------- */
.har-formulir-page {
    page-break-before: always;
    margin-bottom: 12px;
}
.har-formulir-wrapper {
    font-size: 8px;
    line-height: 1.1;
    color: #000;
}
.har-formulir-wrapper table {
    width: 100%;
    border-collapse: collapse;
}
.har-formulir-wrapper .table-full {
    width: 100%;
    border-collapse: collapse;
}
.har-formulir-wrapper .table-bordered {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
}
.har-formulir-wrapper .table-bordered th,
.har-formulir-wrapper .table-bordered td {
    border: 1px solid #000;
    padding: 2px 4px;
    vertical-align: middle;
}
.har-formulir-wrapper .kop-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-bottom: 0;
}
.har-formulir-wrapper .kop-table td {
    border: 1px solid #000;
    padding: 2px 4px;
    vertical-align: middle;
}
.har-formulir-wrapper .kop-table img {
    height: 26px !important;
}
.har-formulir-wrapper .kop-meta {
    font-size: 7.5px;
}
.har-formulir-wrapper .kop-meta td {
    border: 1px solid #000;
    padding: 1px 3px;
    font-size: 7.5px;
}
.har-formulir-wrapper .title-banner {
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #000;
    padding: 3px;
    text-align: center;
    font-weight: bold;
    font-size: 10px;
    letter-spacing: 0.5px;
    background: #f8fafc;
}
.har-formulir-wrapper .specs-table {
    width: 100%;
    border-collapse: collapse;
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #000;
    font-size: 8px;
}
.har-formulir-wrapper .specs-table td {
    padding: 2px 4px;
    border: none;
    border-bottom: 1px solid #000;
}
.har-formulir-wrapper .checklist-table,
.har-formulir-wrapper .matrix-table,
.har-formulir-wrapper .grid-table,
.har-formulir-wrapper .charge-table,
.har-formulir-wrapper .timing-table,
.har-formulir-wrapper .hydro-table,
.har-formulir-wrapper .param-table,
.har-formulir-wrapper .summary-table,
.har-formulir-wrapper .diagram-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    margin-top: -1px;
    font-size: 8px;
}
.har-formulir-wrapper .checklist-table th,
.har-formulir-wrapper .matrix-table th,
.har-formulir-wrapper .grid-table th,
.har-formulir-wrapper .charge-table th,
.har-formulir-wrapper .timing-table th,
.har-formulir-wrapper .hydro-table th,
.har-formulir-wrapper .param-table th,
.har-formulir-wrapper .header-row th {
    border: 1px solid #000;
    background: #f1f5f9;
    padding: 2px 3px;
    font-weight: bold;
    text-align: center;
    font-size: 7.5px;
}
.har-formulir-wrapper .checklist-table td,
.har-formulir-wrapper .matrix-table td,
.har-formulir-wrapper .grid-table td,
.har-formulir-wrapper .charge-table td,
.har-formulir-wrapper .timing-table td,
.har-formulir-wrapper .hydro-table td,
.har-formulir-wrapper .param-table td,
.har-formulir-wrapper .summary-table td,
.har-formulir-wrapper .diagram-table td {
    border: 1px solid #000;
    padding: 2px 3px;
    vertical-align: middle;
}
.har-formulir-wrapper .check-val {
    font-weight: bold;
    font-size: 9px;
    text-align: center;
}
.har-formulir-wrapper .legend-box {
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #000;
    padding: 2px 4px;
    font-size: 7.5px;
    font-weight: bold;
}
.har-formulir-wrapper .notes-box {
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #000;
    padding: 2px 4px;
    font-size: 7.5px;
    min-height: 18px;
}
.har-formulir-wrapper .note-bar,
.har-formulir-wrapper .note-banner,
.har-formulir-wrapper .section-bar {
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #000;
    padding: 2px 4px;
    font-size: 7.5px;
    font-weight: bold;
    background: #f8fafc;
}
.har-formulir-wrapper .sign-table {
    width: 100%;
    border-collapse: collapse;
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #000;
    text-align: center;
    font-size: 8px;
}
.har-formulir-wrapper .sign-table td {
    border: 1px solid #000;
    padding: 2px 4px;
    vertical-align: top;
    width: 33.33%;
}
.har-formulir-wrapper .sign-table img {
    max-height: 38px !important;
}
.har-formulir-wrapper .sign-name {
    font-weight: bold;
    text-decoration: underline;
    text-transform: uppercase;
}
.har-formulir-wrapper .matrix-table .pos-col {
    font-weight: bold;
    font-size: 7.5px;
}
.har-formulir-wrapper .matrix-table .notes-col {
    vertical-align: top;
    text-align: left;
    padding: 2px 4px;
    font-size: 7.5px;
}
.har-formulir-wrapper .inspection-row td {
    text-align: left;
    padding: 2px 4px;
    border: 1px solid #000;
    font-size: 7.5px;
}
.har-formulir-wrapper .inspection-box {
    width: 100%;
    border-collapse: collapse;
    border-left: 1px solid #000;
    border-right: 1px solid #000;
    border-bottom: 1px solid #000;
    font-size: 7.5px;
}
.har-formulir-wrapper .inspection-box td {
    padding: 2px 4px;
}
.har-formulir-wrapper .chk-box {
    display: inline-block;
    width: 10px;
    height: 10px;
    border: 1px solid #000;
    vertical-align: middle;
    margin-right: 3px;
    text-align: center;
    line-height: 9px;
    font-size: 8px;
    font-weight: bold;
}
.har-formulir-wrapper .chk-checked {
    background: #000;
    color: #fff;
}
.har-formulir-wrapper .split-table {
    width: 100%;
    border-collapse: collapse;
}
.har-formulir-wrapper .split-table td {
    padding: 0;
    vertical-align: top;
}
.har-formulir-wrapper .split-header {
    background: #f1f5f9;
    font-weight: bold;
    text-align: center;
    padding: 2px 4px;
    border: 1px solid #000;
    font-size: 7.5px;
}
.har-formulir-wrapper .sig-container {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
}
.har-formulir-wrapper .sig-container td {
    border: 1px solid #000;
    padding: 2px 4px;
    vertical-align: top;
}
.har-formulir-wrapper .sig-space {
    height: 36px;
}
.har-formulir-wrapper .sig-space img {
    max-height: 34px !important;
}


