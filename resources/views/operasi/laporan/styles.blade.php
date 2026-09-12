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
/* Corporate cover — first printed sheet, report starts on the next page. */
.op-cover { page-break-after: always; border: 4px double #1e293b; text-align: center; padding: 48px 30px; }
.op-cover img { height: 60px; }
.op-cover .op-cover-org { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #1e293b; margin-top: 10px; }
.op-cover .op-cover-sub { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-top: 4px; }
.op-cover .op-cover-rule { width: 120px; height: 4px; background: #1e293b; margin: 26px auto; }
.op-cover .op-cover-title { font-size: 30px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #0f172a; line-height: 1.2; }
.op-cover .op-cover-unit { display: inline-block; background: #1e293b; color: #fff; font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; padding: 12px 32px; margin: 20px 0; }
.op-cover .op-cover-period { font-size: 15px; color: #334155; }
.op-cover .op-cover-footer { font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; color: #0f172a; margin-top: 44px; }
.op-cover .op-cover-footer small { display: block; font-size: 10px; font-weight: normal; letter-spacing: 1px; color: #64748b; margin-top: 4px; }
