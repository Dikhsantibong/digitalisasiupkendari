---
paths:
  - 'app/Http/Controllers/K3/**'
---

# Controllers K3

## K3 laporan: dokumen penuh editable + monitoring badge
Laporan K3 = SATU dokumen penuh editable (K3\DocumentController → k3/laporan/document), pola sama HAR: K3ReportBuilder merangkum semua input bulanan → K3DocumentBuilder + K3DocumentGridBuilder, diedit teks (PDF) / spreadsheet grid (Excel) via komponen bersama resources/js/components/document/document-editor.tsx, disimpan k3_document_records (1 per unit+type+periode), PDF via dompdf dari konten tersunting (reuse Operasi\DocumentGridBuilder::gridToHtml). No. Dokumen ISO (SMT-FM-AK3-*) default config('k3.document.numbers'), editable di dokumen — JANGAN auto-generate. View gated k3.laporan.view; simpan k3.input.write (Manager UL view-only). Monitoring (K3\MonitoringController + K3MonitoringService): status kadaluarsa sertifikat/APAR = badge dihitung dari uji_ulang/exp_date vs hari ini, ambang config('k3.expiry_warning_days')=60 — statusFor() menerima Carbon\CarbonInterface (dates project immutable), BUKAN Illuminate\Support\Carbon. Label sistem, bukan push. Semua transaksi K3 pakai kolom year+month langsung (bukan report_period_id). Model EmergencyEquipment WAJIB $table='emergency_equipments' (Equipment uncountable).
