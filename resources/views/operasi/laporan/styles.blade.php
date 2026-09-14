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
