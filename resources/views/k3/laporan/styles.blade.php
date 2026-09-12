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
/* Corporate cover — first printed sheet, report starts on the next page. */
.k3-cover { page-break-after: always; border: 4px double #1e293b; text-align: center; padding: 40px 30px; }
.k3-cover .k3-cover-org { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #1e293b; margin-top: 10px; }
.k3-cover .k3-cover-sub { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-top: 4px; }
.k3-cover .k3-cover-rule { width: 120px; height: 4px; background: #1e293b; margin: 26px auto; }
.k3-cover .k3-cover-title { font-size: 30px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #0f172a; line-height: 1.2; }
.k3-cover .k3-cover-unit { display: inline-block; background: #1e293b; color: #fff; font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; padding: 12px 32px; margin: 20px 0; }
.k3-cover .k3-cover-period { font-size: 15px; color: #334155; }
.k3-cover .k3-cover-footer { font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; color: #0f172a; margin-top: 40px; }
.k3-cover .k3-cover-footer small { display: block; font-size: 10px; font-weight: normal; letter-spacing: 1px; color: #64748b; margin-top: 4px; }
