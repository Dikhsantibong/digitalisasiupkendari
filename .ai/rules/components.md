---
paths:
  - 'resources/js/pages/**, resources/js/components/**'
---

# Components

## Desktop input/jadwal tables use the `excel-sheet` class
Every desktop input or jadwal sheet table gets `className="excel-sheet …"` (resources/css/app.css). The class gives a black grid (2px outer border), yellow thead headers (components layer, so a th with its own bg-*/text-* keeps it), borderless cell inputs with a yellow-50 focus, and no blue focus ring. It skips the phone card layout (table[data-mc]). react-data-grid sheets get the same look from OPERASI_GRID_STYLES (components/operasi/grid.tsx). Do not tag kop/document tables (tbody only) or the hidden print copy inside .print-container.

## Every input page has an Excel export (generic fallback in the top bar)
Keputusan user (2026-11-06): every input / jadwal / formulir / Pengusahaan / Operator page must offer Excel. Pages with their own export keep it (ExcelJS in resources/js/lib/*-excel.ts, mirroring the PDF; e.g. lib/kondisi-abnormal-excel.ts with the eviden photos). Any other such page gets components/generic-excel-button.tsx in AppSidebarHeader (desktop only): it appears when the page shows at least one table and no button of its own containing "Excel", and lib/table-excel.ts exports the visible tables as shown (kop via buildDocumentHeader, merged th/td, input/textarea/select values, ✓ for ticked boxes, the heading above each table as caption; phone card tables [data-mc] and nested tables skipped). Pages built on react-data-grid (div grid, virtualised) are not covered by the fallback — give them their own export. A new page with a real export should label its button "Excel" so the fallback hides.
