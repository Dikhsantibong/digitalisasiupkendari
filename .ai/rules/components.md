---
paths:
  - 'resources/js/pages/**, resources/js/components/**'
---

# Components

## Desktop input/jadwal tables use the `excel-sheet` class
Every desktop input or jadwal sheet table gets `className="excel-sheet …"` (resources/css/app.css). The class gives a black grid (2px outer border), yellow thead headers (components layer, so a th with its own bg-*/text-* keeps it), borderless cell inputs with a yellow-50 focus, and no blue focus ring. It skips the phone card layout (table[data-mc]). react-data-grid sheets get the same look from OPERASI_GRID_STYLES (components/operasi/grid.tsx). Do not tag kop/document tables (tbody only) or the hidden print copy inside .print-container.
