---
paths:
  - 'resources/js/components/app-sidebar.tsx, app/Http/Controllers/LaporanHubController.php, app/Http/Controllers/SlaController.php'
---

# Controllers Http Controllers

## Project Leader: one Laporan menu (laporan.hub) + SLA gabungan (sla.gabungan)
Keputusan user (2026-11-18): an account holding laporan.hub (Project Leader; not Super Admin) gets no per-module "Laporan" sidebar items — instead a "Laporan" group with Laporan (/laporan, LaporanHubController: every viewable ReportModule grouped by module, status + document/PDF links + Resume Bulanan) and SLA (/sla/gabungan, SlaController::combinedIndex). sla.gabungan opens only the Laporan SLA gabungan (SlaReport::allowsCombined) — one unit or unit_id=all (one PDF: per unit cover + body; Excel: Sampul & SLA sheet per unit; combinedData keeps the single-unit shape {unit, cover, slas} and returns {units: [...]} for all); the SLA pages per divisi stay SUPER_ADMIN_ONLY. Both permissions granted by migration 2026_11_18_152323 (never re-run RolePermissionSeeder).

## SLA pages are editable documents (sla_documents)
Keputusan user (2026-11-19): /sla/{divisi} and /sla/gabungan render DocumentEditor (components/laporan/sla-document.tsx) with the deferred body from SlaController::documentProps (DefersReportDocument) — SlaDocumentBuilder::template() = the reports/sla-pdf view itself per unit, scoped under `.sla-doc`, one `.sla-doc-unit` block (id sla-unit-{id}, data-unit-id) per unit, the printed-on `.foot` removed. Saved in sla_documents (scope = divisi key | gabungan, unit_id null = semua unit, month, year, format html|grid, BODY_VERSION) via sla.document.store / sla.document.regenerate (gated like the page + not read-only). pdf()/combinedPdf() print a current saved document (SlaDocumentBuilder::pdf: per unit block the automatic cover for the gabungan, then the edited block landscape via reports/sla-document-pdf), otherwise the original template path unchanged. The "Excel SLA (data)" button keeps the formatted ExcelJS export of the computed values; the per-criterion sources table lives in a <details> under the editor.
