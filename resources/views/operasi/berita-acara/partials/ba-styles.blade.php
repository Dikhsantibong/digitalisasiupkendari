/* Shared Berita Acara styles — used by the editor (content_style) and the PDF
   shell, so the edited HTML renders identically in both. */
* { font-family: 'DejaVu Sans', Arial, sans-serif; }
body { font-size: 11px; color: #000; }
.ba-org { text-align: center; font-weight: bold; line-height: 1.35; }
.ba-org small { font-weight: normal; }
.ba-meta { border: 1px solid #000; border-collapse: collapse; width: 100%; margin-top: 8px; }
.ba-meta td { border: 1px solid #000; padding: 3px 6px; }
.ba-title { text-align: center; font-weight: bold; font-size: 12px; }
.ba-hr { border: none; border-top: 1px solid #000; }
.ba-rows { width: 100%; border-collapse: collapse; margin-top: 10px; }
.ba-rows td { padding: 2px 4px; }
.ba-rows .label { width: 70%; }
.ba-rows .val { text-align: right; white-space: nowrap; }
.ba-rows .sub td { border-top: 1px solid #000; font-weight: bold; }
.ba-rows .indent { padding-left: 18px; }
.ba-data { width: 100%; border-collapse: collapse; margin-top: 8px; }
.ba-data th, .ba-data td { border: 1px solid #000; padding: 3px 4px; text-align: right; }
.ba-data th { background: #eee; text-align: center; }
.ba-data td.j { text-align: left; }
.ba-data tr.total td { font-weight: bold; background: #f5f5f5; }
.ba-note { margin-top: 12px; }
.ba-sign { width: 100%; margin-top: 28px; border-collapse: collapse; }
.ba-sign td { width: 50%; text-align: center; vertical-align: top; }
.ba-sign .name { margin-top: 55px; font-weight: bold; text-decoration: underline; }
.ba-feeder { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
.ba-feeder th, .ba-feeder td { border: 1px solid #000; padding: 3px 4px; }
.ba-feeder th { background: #eee; text-align: center; }
.ba-feeder td.c { text-align: center; }
.ba-feeder td.r { text-align: right; }
.ba-feeder td.l { text-align: left; }
.ba-feeder tr.total td { font-weight: bold; background: #f5f5f5; }
.ba-flowmeter { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 8px; }
.ba-flowmeter th, .ba-flowmeter td { border: 1px solid #000; padding: 2px 2px; }
.ba-flowmeter th { background: #f0f0f0; text-align: center; font-size: 8px; font-weight: bold; }
.ba-flowmeter td.c { text-align: center; }
.ba-flowmeter td.r { text-align: right; font-family: monospace; }
.ba-flowmeter td.l { text-align: left; }
.ba-flowmeter td.pemakaian, .ba-flowmeter th.pemakaian { background: #fff59d; }
.ba-flowmeter tr.total td { font-weight: bold; background: #e0e0e0; }

