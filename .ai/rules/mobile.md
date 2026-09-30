---
paths:
  - 'resources/js/layouts/mobile/**'
---

# Mobile

## Menu lapangan: izin per halaman + registry mobile modular
Setiap halaman input Operasi (14) & HAR input/formulir (27) punya izin sendiri `operasi.lapangan.{key}` / `har.lapangan.{key}` (PermissionName::operasiLapangan()/harLapangan(), grup Role & Akses "Operasi/Pemeliharaan — Input Lapangan"); controller cek via trait `Concerns\AuthorizesFieldInput::allowsFieldInput($user, izinModul, izinHalaman)` — TL tetap lewat izin modul. Menu HP didaftarkan di `layouts/mobile/menus/{umum,operasi,pemeliharaan}.ts` (group, permission, coveredBy); sidebar desktop membangun grup dari FIELD_MENUS. Halaman yang diisi lapangan WAJIB punya cabang `useCompactLayout()` tanpa tabel, pakai komponen `components/mobile/*` (MobileRowEditor, MobileRecordList, MobileGridForm, DayStrip, ChoiceChips, StickyActionBar); OperasiGrid otomatis jadi form per-tanggal di HP. Desktop tidak boleh berubah: semua tampilan HP di balik `compact`.

## Phone menus follow the two accesses (Akses 1 vs Akses 2)
Phone shells are listed in layouts/mobile/modules.ts.
- The `k3-pengusahaan` shell (roles tl_k3, staf_k3) builds its menus from PENGUSAHAAN_MENUS (menus/k3-pengusahaan.ts), so a registered K3 Pengusahaan page appears on the phone automatically, gated by `k3.pengusahaan.view`.
- The operator shell includes LAPORAN_PROJECT_MENUS (menus/laporan-project.ts), gated by `{m}.laporan.view`, so only the Project Leader (Akses 1) sees them.
- Never add a Pengusahaan menu to an Akses 1 shell, or a Laporan Project menu to the Pengusahaan shell.
- Pengusahaan K3 sheets use components/pengusahaan/k3-sheet.tsx (desktop table plus phone MobileRowEditor) and k3-cells.tsx (CellSelect, CellDate stored as DD/MM/YYYY, K3_OPTIONS).

## PdM & Logistik phone shells
tl_logistik (koordinator/office logistik) has a phone shell from menus/logistik.ts, and tl_pdm (koordinator/pic pdm) has one from menus/pdm.ts. Groups are log-* and pdm-*, gated by {m}.input.view / {m}.laporan.view. pdm/(jadwal|input) and logistik/(jadwal|input) are in TABLE_CARD_PAGES, so row tables become cards automatically. Day grids get their own compact branch instead:
- every Logistik grid sheet (LogistikJadwalSheetPage, 8 layouts) renders components/logistik/jadwal-sheet-mobile.tsx (date strip + code chips; jadwal sheets are view-first, input sheets open for input);
- the 4 PdM jadwal pages and pdm/input/realisasi-prediktif use MobileTimelineForm.
Desktop output must stay unchanged; phone views sit behind useCompactLayout. A new PdM/Logistik page must be added to these menus.

## Operasi phone shells (Akses 1 & Akses 2)
koordinator_operasi → shell `operasi-project` (menus/operasi-project.ts: Jadwal op-jadwal, Input op-input from OPERASI_MENUS coveredBy operasi.input.view + Instruksi Kerja, Laporan). tl_operasi & staf_operasi → shell `operasi-pengusahaan` (menus/operasi-pengusahaan.ts, built from PENGUSAHAAN_MENUS module operasi + Laporan). Manager UL has NO phone shell (full app, read-only). `operasi/(jadwal|input)` and `pengusahaan/operasi` are in TABLE_CARD_PAGES. Operasi jadwal grids have their own compact branch: monthly → MobileTimelineForm/MobileDayValuesForm (monthTimelineDays, fieldsOf for per-row fields), yearly months → YEAR_MONTH_DAYS, yearly M1–M4 weeks → YEAR_WEEK_DAYS + weekKeyOf (`{month}-{week}` keys). Commissioning Test Mesin → cards with status ChoiceChips.
