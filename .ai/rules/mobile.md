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
