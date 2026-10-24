---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Per-unit switch: operators' Pengusahaan Operasi input (units.operator_pengusahaan)
Whether a unit's operators/project leader fill the Pengusahaan Operasi inputs is a per-unit setting in Master Unit (section "Hak Akses Unit", units.operator_pengusahaan, default true; the containerized units start false). Enforced twice: AccessControl::resolvePermissionNames drops operasi.pengusahaan.input when none of the user's units allows it (menus disappear), and middleware EnsureOperatorPengusahaanUnit (web) refuses operasi.pengusahaan.* for the unit_id asked (or the default first visible active unit) when that unit is off — only for users without operasi.pengusahaan.view/.write (TL & Staf unaffected). Per-role grants stay in Role & Akses. Future per-unit access switches belong in that Master Unit section with the same two-layer enforcement.
