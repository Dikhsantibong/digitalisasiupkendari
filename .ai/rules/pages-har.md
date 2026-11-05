---
paths:
  - 'resources/js/components/har/**, resources/js/components/pengusahaan/har-formulir.tsx, resources/js/pages/har/**'
---

# Pages Har

## HAR input tables use the Excel sheet style
Desktop input tables in Pemeliharaan (har-formulir, lembar-page, tabel-page, patrol-check-parameter, 5S5R, logbook mutasi) use the Excel look: table `border-collapse border-2 border-black dark:border-white`, cells `border border-black dark:border-white/60`, yellow headers (`bg-yellow-200 text-black`), borderless inputs with `focus:bg-yellow-50 focus-visible:ring-0` (no blue focus ring). Patrol Check Parameter keeps its coloured group headers but with black borders. Phone (`compact`) branches keep the card editors.
