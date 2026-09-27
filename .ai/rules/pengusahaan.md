---
paths:
  - 'resources/js/pages/pengusahaan/**'
---

# Pengusahaan

## Pengusahaan (Akses 2) pages live in pages/pengusahaan/{modul}/
Akses 2 — Pengusahaan pages go in resources/js/pages/pengusahaan/{k3|har|operasi}/{nama-halaman}/index.tsx, one folder per page. Controllers render 'pengusahaan/{modul}/{nama}/index'. Do NOT put them under pages/{modul}/pengusahaan/, which is Akses 1 territory. pages/pengusahaan/index.tsx is the shared hub. Route names stay {modul}.pengusahaan.{nama}.*. Pengusahaan input pages have no signature (tanda tangan/paraf) inputs or signature blocks.
