---
paths:
  - 'resources/js/pages/k3/**'
---

# Pages K3

## K3 & pengusahaan K3 phone layout: no tables
On phones (useCompactLayout), K3 and pengusahaan/k3 pages must not show input tables. AppLayout wraps these pages (TABLE_CARD_PAGES regex) in MobileTableCards, which turns any <table> into one labelled card per row. It only activates on the phone; opt a table out with data-keep-table. Day × row matrices get a dedicated compact branch: MobileTimelineForm (tick RENC/REAL), MobileMatrixForm (value/shift per date), or DayStrip. The desktop JSX stays untouched in the `compact ? mobile : desktop` ternary. Fixed-width kops and grids must stay responsive: phone classes first, the original desktop classes behind `sm:`.
