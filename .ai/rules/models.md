---
paths:
  - 'app/Models/**'
---

# Models

## Modul OPERASI — pemetaan master & konvensi scope
Modul OPERASI memakai master existing via FK, JANGAN buat ulang: `unit_id` -> tabel `units` (PLTD Poasia = 1 baris; PLTD-POASIA-CONT unit terpisah), `engine_id` -> `machines`, `employee_id` -> `employees`, identitas kop/BA dari `service_units`. Scoping unit lewat `role_assignments` + AccessControl (BUKAN pivot `user_units`). Semua tabel transaksi/master turunan pakai trait `App\Models\Concerns\BelongsToUnit` (relasi unit() + scopeVisibleTo). `machines` sudah ditambah kolom `fuel_type` (enum FuelType) + pivot `machine_lubricant_type`; nilainya diisi user per mesin via form edit mesin (jangan diasumsikan). Akses modul hanya role `tl_operasi` (permission `operasi.*` di PermissionName, grup Operasi); di-grant hanya ke TeamLeaderOperasi. Registry modul: tabel `work_modules` (seed `operasi`).

## Report-signer jabatan are canonical & one active holder per unit
employees.position values in App\Enums\EmployeePosition (Manager UL, TL Pemeliharaan/Operasi/K3, Koordinator ×5, Project Leader, Office ×5, PIC PDM) must match exactly. Employee::saving derives division + singleton_key ("unit:{id}:{jabatan}", Manager UL "su:{id}:Manager UL", null when inactive); a unique index enforces one active holder. Seeders run WithoutModelEvents, so set these via Employee::signerAttributes() (see EmployeeSeeder). User→Employee is employees.user_id (User::employee()); users.employee_id is only a staff number, not a relation.
