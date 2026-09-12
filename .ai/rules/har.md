---
paths:
  - 'app/Services/Har/**'
---

# Har

## Modul PEMELIHARAAN (HAR) — seam WorkOrderSource & reuse fondasi Operasi
Akses WO/SR HANYA lewat interface `App\Services\Har\WorkOrderSource` (jangan query tabel work_orders/service_requests langsung dari controller/laporan). Default binding di AppServiceProvider = `ManualWorkOrderSource` (baca tabel lokal per unit+report_period). `WpcWorkOrderSource` = placeholder (throw) untuk integrasi DB WPC PLN nanti — swap binding saja, tanpa ubah controller/laporan. Kolom `source` (enum WorkOrderSource: manual|wpc) bedakan asal. Config WPC di `config/har.php` sengaja kosong; jangan hardcode IP 192.168.3.85.
Pakai ulang fondasi modul Operasi (JANGAN duplikasi): RBAC (PermissionName `har.*`, grup Pemeliharaan; TL Pemeliharaan penuh, Manager UL view), `work_modules` (seed `pemeliharaan`), `report_periods` (per unit+bulan+tahun), document template engine (`document_templates`/`DocumentTemplateService`/`DocumentGridBuilder`), report registry (pola `OperasiReport`+`ReportRegistry`), helper `App\Support\Indonesian`, dan komponen grid (`components/operasi/grid.tsx` + `useExcelPaste`). Master pemeliharaan (maintenance_types/cycles, wo_statuses, work_groups, sr_categories) = GLOBAL (tanpa unit_id); transaksi ber-unit pakai trait `BelongsToUnit`. Nomor dokumen ISO tetap per jenis sheet (editable, bukan auto-generate). Foto lampiran ke storage (path), bukan blob.
