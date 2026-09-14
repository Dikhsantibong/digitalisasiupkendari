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
.k3-fig img { max-width: 100%; max-height: 220px; }
.k3-fig figcaption { font-size: 10px; }
/* Corporate cover — redesigned matching PLN + MKP branding */
.k3-cover {
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
