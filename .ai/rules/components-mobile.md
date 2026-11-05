---
paths:
  - 'resources/js/components/mobile/**'
---

# Components Mobile

## Long tables on phones: render one row at a time, fold, batch
Phone views of long editable tables must not render every row's inputs.
- Shared/custom form pages: add a compact branch with components/mobile/compact-rows.tsx (MobileCompactRows). Each row is one collapsed line (title/subtitle from lib/mobile-row-summary.ts). Only the opened row renders the page's own cell editors. Features: search, batches of 15, titled sections fold one at a time, `focus` opens the row just added, `quick` puts tick-only controls inline. Used by the Logistik/PdM form components, Rekomendasi, Kesiapan APD, Sample, PTW.
- Pages left to the automatic table→cards (TABLE_CARD_PAGES): MobileTableCards folds cards with more than 3 fields to row no. + 2 key fields (tap to open). It renders 15 rows per batch ("Tampilkan … lagi" is the table's ::after), opens a single newly added row, and labels body rowSpan correctly.
- Grid sheets (Logistik jadwal sheets, HAR lembar-page) show one section at a time; row detail inputs open on demand.
All of this sits behind compact/isMobile; desktop tables stay unchanged.

## Halaman input HP operator: langsung form, filter dilipat
Keputusan user (2026-10-29): di HP operator harus langsung mengisi. Cabang compact halaman input TIDAK menampilkan PageHeader/deskripsi, kartu KPI/tile, tombol PDF/Excel, atau catatan; filter Unit/Bulan/Tahun memakai `components/mobile/compact-filters.tsx` (satu baris ringkas, diketuk untuk ganti). Pilihan yang sering diganti operator (mis. Mesin di Patrol Check) tetap tampil. Kondisi Abnormal: form "Laporan baru" inline di HP (EntryDialog `inline`), desktop = tabel isian (`sync`). Halaman tidak mengisi baris kosong berulang (FLM hanya 1 baris kosong bila bulan kosong; padding MIN_ROWS hanya untuk PDF).

## Phone input pages drop description, unit/period filters and summary cards (useSimplifiedMobileInput)
Keputusan user (2026-11-05): on a phone, every module's input / jadwal / formulir page (har, k3, logistik, pdm, operasi input-jadwal-formulir, pengusahaan har/k3/operasi except Berita Acara, BA & TUG 9, and operator/*) is filled straight away: no page description, no Unit / Unit Pembangkit / Bulan / Tahun filter (the account's unit, the current period) and no summary cards. Handled centrally by `useSimplifiedMobileInput()` (hooks/use-mobile-module.ts: inside the mobile shell AND an input-page component) in OperasiSelect, MetaField (K3 formulir), PageHeader (only `actions` stay), SummaryCard and the local StatTile/Kpi tiles: they render `<span data-mobile-hidden hidden />`, and app.css hides any element whose children are all such markers (empty filter bars / card grids). Do not wrap a filter OperasiSelect in its own `<div className="col-span-…">` — pass `labelClassName` instead, or the wrapper survives as an empty box. Users without a shell (Manager UL, Super Admin) and the Laporan pages keep their filters. Selects that are part of the input (Mesin, Minggu, Periode minggu, Lokasi, Kategori) stay. PDF/Excel buttons are not hidden generically (download icons also open attachments).
