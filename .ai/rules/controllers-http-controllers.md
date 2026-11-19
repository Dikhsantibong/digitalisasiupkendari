---
paths:
  - 'resources/js/components/app-sidebar.tsx, app/Http/Controllers/LaporanHubController.php, app/Http/Controllers/SlaController.php'
---

# Controllers Http Controllers

## Project Leader: one Laporan menu (laporan.hub) + SLA gabungan (sla.gabungan)
Keputusan user (2026-11-18): an account holding laporan.hub (Project Leader; not Super Admin) gets no per-module "Laporan" sidebar items — instead a "Laporan" group with Laporan (/laporan, LaporanHubController: every viewable ReportModule grouped by module, status + document/PDF links + Resume Bulanan) and SLA (/sla/gabungan, SlaController::combinedIndex). sla.gabungan opens only the Laporan SLA gabungan (SlaReport::allowsCombined) — one unit or unit_id=all (one PDF: per unit cover + body; Excel: Sampul & SLA sheet per unit; combinedData keeps the single-unit shape {unit, cover, slas} and returns {units: [...]} for all); the SLA pages per divisi stay SUPER_ADMIN_ONLY. Both permissions granted by migration 2026_11_18_152323 (never re-run RolePermissionSeeder).
