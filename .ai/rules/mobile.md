---
paths:
  - 'resources/js/layouts/mobile/**'
---

# Mobile

## Menu lapangan: izin per halaman + registry mobile modular
Setiap halaman input Operasi (14) & HAR input/formulir (27) punya izin sendiri `operasi.lapangan.{key}` / `har.lapangan.{key}` (PermissionName::operasiLapangan()/harLapangan(), grup Role & Akses "Operasi/Pemeliharaan — Input Lapangan"); controller cek via trait `Concerns\AuthorizesFieldInput::allowsFieldInput($user, izinModul, izinHalaman)` — TL tetap lewat izin modul. Menu HP didaftarkan di `layouts/mobile/menus/{umum,operasi,pemeliharaan}.ts` (group, permission, coveredBy); sidebar desktop membangun grup dari FIELD_MENUS. Halaman yang diisi lapangan WAJIB punya cabang `useCompactLayout()` tanpa tabel, pakai komponen `components/mobile/*` (MobileRowEditor, MobileRecordList, MobileGridForm, DayStrip, ChoiceChips, StickyActionBar); OperasiGrid otomatis jadi form per-tanggal di HP. Desktop tidak boleh berubah: semua tampilan HP di balik `compact`.
