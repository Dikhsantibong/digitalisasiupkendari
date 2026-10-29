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
