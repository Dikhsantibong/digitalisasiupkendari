---
paths:
  - 'app/Services/Monitoring/**'
---

# Monitoring

## Menu Monitoring: input catalog & permission
Menu Monitoring (routes monitoring.{index,input,laporan}, MonitoringController) is gated by `monitoring.view` (PermissionName::MonitoringView, group Monitoring; only Super Admin by default, grantable per role) and always scoped to Unit::visibleTo. Kelengkapan Input reads tables directly through App\Services\Monitoring\InputCatalog: when a module gets a new input page/table, add it there (kind month|year|date|daily|daily_day|daily_engine|period|count, route of the page). MonitoringTest asserts every catalog route & table exists. Verifikasi Laporan (ReportMonitoring) derives status/age/turn from report_workflows + steps + logs; "macet" = waiting ≥ 3 days at one step.
