---
paths:
  - 'app/**'
---

# App

## Authorise by permission, never by role name
Never branch on a role name (`$user->hasRole(...)`) to authorise a business action — check a permission or a policy instead (`$user->can('report_unit.approve', $unit)`). `hasRole()` exists only for presentation and the Super Admin gate bypass.

The permission catalogue lives in `App\Enums\PermissionName`; role→permission defaults live in `App\Enums\RoleName::defaultPermissions()`. A new module adds enum cases + mapping and re-runs `RolePermissionSeeder` — no migration, no new authorisation code.

Unit/UL scope is resolved only in `App\Services\AccessControl` (memoised per request) and applied to queries via the `visibleTo($user)` scope on Unit, ServiceUnit, and ActivityLog. Do not re-derive scope in a controller. Call `$user->forgetAccessCache()` after changing assignments or role permissions.

## UP Kendari view-only roles use the Portal and are blocked from writes by middleware
Manager UP, TL/Asman Operasi/Pemeliharaan/K3 UP are Global-scope roles with `portal.view` + `portal.readonly`. `User::isReadOnly()` drives `EnsureReadOnlyAccess` (rejects every non-GET except logout/notifications/profile/password/2FA) — when adding a new account-level POST route they need, add it to that allowlist. `usesPortal()` (also Manager UL, not read-only) redirects dashboard → `portal.index` and renders PortalLayout. Pages must hide edit buttons via `can_write && ! isReadOnly()`; bidang filtering goes through `Monitoring\ModuleAccess`.

## PRD.md is the only application-flow document
Keputusan user (2026-11-04): seluruh dokumen alur (concept.md, modul-*.md, prompt-implementasi-*.md) digabung ke `PRD.md` di root. Setiap perubahan alur, role, permission, menu, atau modul WAJIB di-update di PRD.md (bagian modul di §8, aturan hitung §10, isu terbuka §15, keputusan user di §16 Riwayat Keputusan). JANGAN membuat lagi file modul-*.md / prompt-implementasi-*.md terpisah. `design.md` tetap terpisah (design system UI). Komentar kode merujuk ke PRD.md §x.

## Short account e-mails (App\Support\AccountEmail)
Keputusan user (2026-11-19): application accounts use `{jabatan}.{unit}@upkendari.co.id` with abbreviations (pl, sl, tl.ops/har/ophar/k3, koord.*, ofc.*, pic.pdm, staf.*, opN, unit; unit = code without the PLTD prefix and hyphens, other types keep theirs: poasiacont, pltgkolaka; UL accounts admin.{UL} / mgr.{UL}). Never build an account e-mail by hand: use AccountEmail::forUnit($prefix, $unitCode) or AccountEmail::short($email) (idempotent; personal names keep the full name to stay unique). Existing accounts were renamed by migration 2026_11_19_121442; FortifyServiceProvider::authenticateUsing also accepts a legacy address (mapped with AccountEmail::short, incl. the TL Ophar variant) — keep that fallback.
