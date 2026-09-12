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
/* Corporate cover — first printed sheet, report starts on the next page. */
.har-cover { page-break-after: always; border: 4px double #1e293b; text-align: center; padding: 48px 30px; }
.har-cover img { height: 60px; }
.har-cover .har-cover-org { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #1e293b; margin-top: 10px; }
.har-cover .har-cover-sub { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-top: 4px; }
.har-cover .har-cover-rule { width: 120px; height: 4px; background: #1e293b; margin: 26px auto; }
.har-cover .har-cover-title { font-size: 30px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #0f172a; line-height: 1.2; }
.har-cover .har-cover-unit { display: inline-block; background: #1e293b; color: #fff; font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; padding: 12px 32px; margin: 20px 0; }
.har-cover .har-cover-period { font-size: 15px; color: #334155; }
.har-cover .har-cover-footer { font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; color: #0f172a; margin-top: 44px; }
.har-cover .har-cover-footer small { display: block; font-size: 10px; font-weight: normal; letter-spacing: 1px; color: #64748b; margin-top: 4px; }
.har-toc { width: 100%; border-collapse: collapse; margin-top: 4px; }
.har-toc td { padding: 2px 4px; }
.har-toc td.n { width: 28px; font-weight: bold; }
.har-figs { margin-top: 6px; }
.har-fig { display: inline-block; width: 48%; vertical-align: top; border: 1px solid #000; padding: 4px; margin: 0 2px 6px 0; }
.har-fig img { max-width: 100%; max-height: 220px; }
.har-fig figcaption { font-size: 10px; }
