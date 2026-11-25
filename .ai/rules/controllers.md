---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Instruksi Kerja input is shared across modules
IK Pemeliharaan (har_instruksi_kerjas) and IK Operasi (operasi_instruksi_kerjas) share App\Http\Controllers\BaseInstruksiKerjaController (validation, store/destroy/pdf, present, signatories). The PDF/document view is resources/views/har/instruksi-kerja/*. The React editor is components/instruksi-kerja/editor-page.tsx. A module only adds a model + migration (same columns), templates (App\Support\{Har,Operasi}IkTemplates, parts built with HarIkTemplates::section), a subclass controller (model, page, route prefix, permissions, preparedBy jabatan/position) and a thin page passing routes + copy. Routes are {module}.input.instruksi-kerja.{index,store,pdf,destroy}; destroy takes a numeric id. Change the editor/preview and document.blade.php together.

## Jadwal Kegiatan of every divisi is shared (Harian, Mingguan, Bulanan, Non Rutin)
Operasi (operasi_jadwal_kegiatans, operasi.jadwal.kegiatan.*), Pemeliharaan (har_jadwal_harians + grup, har.jadwal.harian.*) and K3 (k3_kegiatan_rutins, k3.jadwal.kegiatan-rutin.*) extend App\Http\Controllers\BaseJadwalKegiatanController and render components/jadwal/kegiatan-jadwal-page.tsx through a thin page passing titles + routes. Groups come from App\Support\JadwalKegiatanGroups (harian, mingguan, bulanan, non_rutin; mirrored in resources/js/lib/jadwal-kegiatan.ts); a row without grup is saved as harian. PdM keeps its own category-header sheet (I. Harian … IV. Non Rutin) and Logistik its LogistikJadwal 'kegiatan' sections (key 'mesin' titled Harian). Add a new divisi by a model with the same columns + a subclass controller + a thin page.
