---
paths:
  - 'app/Http/Controllers/K3/**'
---

# Controllers K3

## K3 laporan: dokumen penuh editable + monitoring badge
Laporan K3 = SATU dokumen penuh editable (K3\DocumentController → k3/laporan/document), pola sama HAR: K3ReportBuilder merangkum semua input bulanan → K3DocumentBuilder + K3DocumentGridBuilder, diedit teks (PDF) / spreadsheet grid (Excel) via komponen bersama resources/js/components/document/document-editor.tsx, disimpan k3_document_records (1 per unit+type+periode), PDF via dompdf dari konten tersunting (reuse Operasi\DocumentGridBuilder::gridToHtml). No. Dokumen ISO (SMT-FM-AK3-*) default config('k3.document.numbers'), editable di dokumen — JANGAN auto-generate. View gated k3.laporan.view; simpan k3.input.write (Manager UL view-only). Monitoring (K3\MonitoringController + K3MonitoringService): status kadaluarsa sertifikat/APAR = badge dihitung dari uji_ulang/exp_date vs hari ini, ambang config('k3.expiry_warning_days')=60 — statusFor() menerima Carbon\CarbonInterface (dates project immutable), BUKAN Illuminate\Support\Carbon. Label sistem, bukan push. Semua transaksi K3 pakai kolom year+month langsung (bukan report_period_id). Model EmergencyEquipment WAJIB $table='emergency_equipments' (Equipment uncountable).

## Dokumen IK & Jadwal Pembuatan IK K3
Input Dokumen IK (k3.input.dokumen-ik.*, DokumenIkController, table k3_dokumen_iks) stores IK documents per unit and report month. `sections` is JSON [{judul, gaya: butir|huruf|angka|paragraf, pengantar, butir[]}]. Starting templates live in App\Support\K3IkTemplates. The printed layout is resources/views/k3/dokumen-ik/document.blade.php, shared by the IK PDF and the Laporan K3; the IK pages are appended after section VII. The React preview in pages/k3/input/dokumen-ik/index.tsx must mirror that Blade. Jadwal Pembuatan IK K3 (k3.jadwal.pembuatan-ik.*, K3JadwalPembuatanIk) is a copy of the HAR Pembuatan IK with its own table; it feeds section 28 of the Laporan K3 as a yearly R/✓ matrix. Section 28 also keeps the monthly Instruksi Kerja K3L table.
