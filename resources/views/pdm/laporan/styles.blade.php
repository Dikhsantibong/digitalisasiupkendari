{{-- Laporan PdM & Maturity Level styles: the shared K3 report look (cover, kop,
     daftar isi, pengesahan) plus the PdM section rules. Each embedded jadwal /
     input table brings its own scoped styles (PdmDocumentBuilder::contentStyles). --}}
@include('k3.laporan.styles')
.pdm-kop-logo-right img { max-height: 42px; max-width: 120px; }
.pdm-toc-group td { font-weight: bold; background: #f8fafc; }
/* Editor only: show where each report page starts. */
.mce-content-body .pdm-section + .pdm-section { margin-top: 24px; padding-top: 12px; border-top: 2px dashed #cbd5e1; }
