---
paths:
  - 'app/Enums/**'
---

# Enums

## Two accesses per module: Akses 1 Laporan Project vs Akses 2 Pengusahaan
Operasi, Pemeliharaan and K3 each have two accesses.
- Akses 1 — Laporan Project: permissions `{m}.input.*`, `{m}.laporan.view` and master. Held by the Koordinator roles (Koordinator and Office accounts) and the Project Leader (`project_leader_operasi`, which reads every laporan).
- Akses 2 — Pengusahaan: permissions `{m}.pengusahaan.view` and `.write`. Held by the TL and Staf roles.
TL keeps `{m}.laporan.view` only to approve (menyetujui) the Laporan Pembangkit; do not give TL input permissions again. Akses 2 pages live in `resources/js/lib/pengusahaan-menus.ts` (PENGUSAHAAN_MENUS): add a page there with its module and section (jadwal, input or formulir), and the hub page (PengusahaanController, route `{m}.pengusahaan.index`) and the sidebar pick it up. Both reports have ONE door: the module's Laporan page (sidebar item "Laporan"), opened with `{m}.laporan.view` OR `{m}.pengusahaan.view`, with each card behind its own permission. Do not add a separate Laporan Pengusahaan menu. Authorise new Akses 2 pages with the module's pengusahaan permission, or with a new permission in the `*Pengusahaan` group.

## Operator: hanya menu inti Operator + Input Operasi (tanpa Pengusahaan)
Keputusan user (2026-10-30): role Operator TIDAK memegang operasi.pengusahaan.input (dicabut dari RoleName + migrasi 2026_10_29_194439) maupun operasi.lapangan.{daily_report,star_stop,feeder,auxiliary,fuel_receipt,resource_pembangkit} (halaman yang pindah ke Pengusahaan; migrasi 2026_10_29_195253 membersihkan DB lama). Operator = menu Operator (Absensi, Logsheet Mesin, Start Stop Engine, Lembar Mutasi, Pengajuan, Resume) + Input Operasi lapangan. Project Leader tetap punya operasi.pengusahaan.input. Start Stop Engine (Operator\StartStopController, operator.start-stop.*, tabel operator_start_stops, izin operator.logsheet.view/write): stop penyebab_stop=gangguan = Jumlah Kali Gangguan (MesinHarianSheet::kaliGangguan / readingsOf), halaman Kali Gangguan read-only (from_start_stop).
