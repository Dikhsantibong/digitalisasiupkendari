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
