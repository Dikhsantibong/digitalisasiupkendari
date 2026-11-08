---
paths:
  - 'app/Services/Dashboard/**'
---

# Dashboard

## Dashboard shows only real data, one deferred prop per module
Keputusan user (2026-11-08): the dashboard never uses dummy/sample figures (DashboardDummyData removed). Each module section is built by App\Services\Dashboard\{Operasi,Har,K3,Logistik,Pdm}Dashboard from the existing input sheets/services (KwhSheet, MesinHarianSheet, PersediaanSheet, KinerjaTermalSheet, WO/SR via report_periods, K3MonitoringService::statusFor, Kondisi Stok form, HarVibration…) — reuse those services, do not re-derive formulas. The payload shape is the contract of resources/js/pages/dashboard.tsx. Controller passes each module as Inertia::defer(…, '<module>') (tests use loadDeferredProps); empty input = 0 / "Belum ada data", KPI without a source = null ("—"), delta null when last month has no figure. Sources listed in PRD.md §7.2.
