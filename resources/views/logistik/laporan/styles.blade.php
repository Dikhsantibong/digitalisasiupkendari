{{-- Laporan Logistik & Gudang styles: the shared K3 report look (cover, kop,
     daftar isi) plus the Lembar Pengesahan and section rules. Each embedded
     jadwal / input table brings its own scoped styles (LogistikDocumentBuilder::contentStyles). --}}
@include('k3.laporan.styles')
.lg-kop-logo-right img { max-height: 42px; max-width: 120px; }
.lg-toc-group td { font-weight: bold; background: #f8fafc; }
.lg-pengesahan { width: 100%; border: 1.5px solid #000; border-collapse: collapse; }
.lg-pengesahan td { vertical-align: top; }
.lg-pengesahan .head td { border-bottom: 1.5px solid #000; vertical-align: middle; padding: 6px 8px; }
.lg-pengesahan .head .logo img { max-height: 46px; max-width: 150px; }
.lg-pengesahan .head .title { text-align: center; font-size: 17pt; font-weight: bold; }
.lg-pengesahan .body { padding: 14px 10px 10px 10px; font-size: 10pt; line-height: 1.6; }
.lg-pengesahan .body h2 { text-align: center; font-size: 14pt; margin: 4px 0 14px 0; }
.lg-pengesahan .center { text-align: center; }
.lg-sign { width: 100%; border-collapse: collapse; }
.lg-sign td { border: 1px solid #000; text-align: center; font-weight: bold; font-size: 9pt; padding: 3px; width: 33.3%; }
.lg-sign td.space { height: 85px; border-bottom: none; border-top: none; }
.lg-sign td.name { border-top: none; vertical-align: bottom; }
/* Editor only: show where each report page starts. */
.mce-content-body .lg-section + .lg-section { margin-top: 24px; padding-top: 12px; border-top: 2px dashed #cbd5e1; }
