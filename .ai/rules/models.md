---
paths:
  - 'app/Models/**'
---

# Models

## Modul OPERASI — pemetaan master & konvensi scope
Modul OPERASI memakai master existing via FK, JANGAN buat ulang: `unit_id` -> tabel `units` (PLTD Poasia = 1 baris; PLTD-POASIA-CONT unit terpisah), `engine_id` -> `machines`, `employee_id` -> `employees`, identitas kop/BA dari `service_units`. Scoping unit lewat `role_assignments` + AccessControl (BUKAN pivot `user_units`). Semua tabel transaksi/master turunan pakai trait `App\Models\Concerns\BelongsToUnit` (relasi unit() + scopeVisibleTo). `machines` sudah ditambah kolom `fuel_type` (enum FuelType) + pivot `machine_lubricant_type`; nilainya diisi user per mesin via form edit mesin (jangan diasumsikan). Akses modul hanya role `tl_operasi` (permission `operasi.*` di PermissionName, grup Operasi); di-grant hanya ke TeamLeaderOperasi. Registry modul: tabel `work_modules` (seed `operasi`).
