---
paths:
  - 'app/Services/Reports/**, app/Http/Controllers/LaporanUlController.php'
---

# Http Controllers

## Laporan UL is a document with its own approval chain (UlReportWorkflow)
Keputusan user (2026-11-18): each Laporan UL opens as a DocumentEditor document (pages/laporan/ul/document.tsx; body by UlReportDocumentBuilder + reports/ul-document/*, stored in ul_report_documents, BODY_VERSION) with approval Admin UL ajukan → one TL per bidang of the UL's induk unit (first active unit; ReportSignatories::holder, delegation-aware) in UlReportWorkflow::CHAINS → Manager UL sahkan → Final. It is SEPARATE from ReportWorkflowService/report_workflows (do not add UL kinds to ReportModule). The panel reuses report-workflow-panel via the optional workflow.action_urls; the Persetujuan inbox appends UlReportWorkflow::queue items carrying action_url/reject_url. TL/Manager UL open only the reports they approve (LaporanUlController::target isApprover); the hub stays laporan_ul.view only.
