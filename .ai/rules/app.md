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

## Administrasi & Keuangan per UL (keuangan.*)
Keputusan user (2026-11-20): routes/keuangan.php (keuangan.cash-card.{index,store,pdf,bukti}, keuangan.supplier.{index,store,update,destroy}) with Keuangan\CashCardController / SupplierController + Concerns\HandlesKeuangan (gate keuangan.* + ServiceUnit::visibleTo). Per UL (service_unit_id): suppliers (Master Supplier: name, email, npwp, ktp_path; used ones are deactivated, never deleted), cash_card_reports (unique UL+month+year, saldo_awal) + cash_card_entries (rows, supplier_id of the same UL only, photos json of public-disk paths under keuangan/cash-card/{ul}/{Y-m}; removed photos are deleted after save). Role jof_administrasi_umum (scope UL, EmployeePosition::JofAdministrasiUmum is UL-level like Manager UL) writes; Manager UL & Admin UL get keuangan.view only (migration 2026_11_20_043000, JofAccountSeeder → jof.{UL}@…). Services\Keuangan\CashCardDocument builds the laporan (running saldo, signers Manager UL / JOF from singleton_key) and per-row Bukti Penerimaan Kas Kecil + Lampiran (row photos + supplier KTP/email/NPWP as data URIs) for views keuangan/cash-card-pdf & keuangan/bukti-pdf.

The Laporan Administrasi Keuangan opened from the Laporan UL card is an editable document (Keuangan\DocumentController, keuangan.document.{show,pdf,store,regenerate}, page keuangan/dokumen with DocumentEditor): Services\Keuangan\KeuanganDocumentBuilder::template = #keu-laporan (.keu-lap, the cash card table) then #keu-bukti (.keu-bukti, page-break-before; photos as /storage URLs, re-embedded as data URIs in pdf()). Saved per UL+month in keuangan_documents; bump KeuanganDocumentBuilder::BODY_VERSION only when old saved docs must be rebuilt.
