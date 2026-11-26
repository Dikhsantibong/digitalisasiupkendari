---
paths:
  - 'resources/js/lib/report-layout-workbook.ts, resources/js/lib/report-book.ts, resources/js/components/laporan/laporan-export-buttons.tsx'
---

# Laporan

## Laporan Excel download = PDF layout
User (2026-11-26): the laporan Excel must look exactly like the PDF. The Laporan page Excel button and the document page "Unduh Excel" use downloadReportLayoutWorkbook(content, content_styles): the document is laid out in a hidden iframe with its styles at A4 width (718px portrait / 1047px landscape for a lembar with a *landscape class, 794px cover), then every cell/text/img/svg is measured and the snapped edges become the sheet's columns/rows (merges, borders, fills, fonts, images). Only an edited/saved Excel grid is written by report-workbook as before. reportBlocks() in report-book.ts is the shared lembar splitter; pageGroups keeps its wrappers in the DOM so they can be measured.
