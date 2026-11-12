---
paths:
  - 'app/Http/Controllers/Portal/**'
---

# Portal

## Manager UL gets the reviewer home in the portal
Keputusan user (2026-11-09): a portal account that is not read-only and holds report_unit.approve (the Manager UL; shared prop auth.reviewer) lands on portal/manager — Perlu Pengesahan Anda (own open steps via report_workflow_steps, with Review/PDF/Sahkan/Tolak posting to the existing report-workflow.* routes), status matrix (ReportMonitoring), riwayat tanda tangan, Resume links. PortalLayout shows that user a short menu (Pengesahan, Arsip Laporan, Resume Bulanan, Status Laporan, Ringkasan Pemantauan; Data Input & Status Input under "Pemantauan Lainnya"). The old Beranda stays at portal.overview (/pantau/ringkasan). View-only UP accounts keep portal/index unchanged. Decide by permission, not role name.
