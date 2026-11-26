---
paths:
  - 'resources/js/pages/*/laporan/index.tsx, resources/js/lib/report-book.ts'
---

# Lib

## Laporan page PDF & Excel buttons
Every "Buka Dokumen …" card on a Laporan page also has PDF & Excel (components/laporan/laporan-export-buttons.tsx). Excel asks the document page for just its deferred document by an Inertia partial reload (X-Inertia-Partial-Data content,content_styles,grid,format,has_saved) and builds the same book as the page's "Unduh Excel" (saved grid, else the PDF layout — see .ai/rules/laporan.md). report-book splits on *-section (incl. op-p-section) / section-box blocks, or on top-level break-before pages (Laporan Pemeliharaan pengusahaan) — keep new report bodies in one of these shapes. Test: tests/Feature/Reports/LaporanHubExportTest.php.
