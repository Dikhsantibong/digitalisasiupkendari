{{-- Laporan UL styles: the shared K3/PdM report look (cover, kop, daftar isi,
     pengesahan) plus the Laporan UL tables, charts and signature block. --}}
@include('k3.laporan.styles')
.pdm-kop-logo-right img { max-height: 42px; max-width: 120px; }
.ul-h { font-size: 10pt; font-weight: bold; color: #0b2545; margin: 10px 0 4px; }
.ul-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.ul-table th, .ul-table td { border: 0.7px solid #000; padding: 2px 4px; font-size: 8pt; }
.ul-table th { background: #fde68a; text-align: center; }
.ul-table td.r { text-align: right; }
.ul-table td.c { text-align: center; }
.ul-table tr.ul-foot td { font-weight: bold; background: #f1f5f9; }
.ul-insights { margin: 2px 0 6px 16px; padding: 0; font-size: 8.5pt; }
.ul-insights li { margin-bottom: 1px; }
.ul-charts { width: 100%; border-collapse: separate; border-spacing: 5px; }
.ul-charts td { width: 50%; vertical-align: top; border: 1px solid #e2e8f0; padding: 4px; }
.ul-charts img { width: 100%; }
.ul-chart-title { font-size: 8.5pt; font-weight: bold; margin-bottom: 2px; }
.ul-empty { color: #64748b; font-style: italic; text-align: center; }
.ul-ttd { width: 100%; border-collapse: collapse; margin-top: 10px; }
.ul-ttd td { text-align: center; vertical-align: top; font-size: 9.5pt; padding: 0 4px; }
.ul-ttd-caption { font-weight: bold; }
.ul-ttd-mark { height: 64px; font-size: 7.5pt; font-style: italic; color: #1f4e79; display: block; padding-top: 18px; }
.ul-ttd-name { font-weight: bold; text-decoration: underline; }
/* Editor only: show where each report page starts. */
.mce-content-body .ul-section + .ul-section { margin-top: 24px; padding-top: 12px; border-top: 2px dashed #cbd5e1; }
