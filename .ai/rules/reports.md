---
paths:
  - 'app/Services/Reports/**'
---

# Reports

## Laporan multi-orientasi: satu section per butir Daftar Isi
Laporan K3 Pembangkit & Operasi Pembangkit: body = satu div per butir Daftar Isi (`.k3-section`/`.op-section`, id `sec-*`), tambah `.k3-landscape`/`.op-landscape` untuk halaman landscape. PDF via `OrientationPdfMerger::renderSections(styles, body, sectionClass, landscapeClass, footer, unnumberedPages: 1)` — mengelompokkan section berurutan per orientasi, merge jadi 1 PDF, sampul tanpa nomor, dan mengisi teks `<a href="#sec-x">…</a>` di Daftar Isi dengan halaman nyata (suffix di luar link, mis. "4.a"). Aturan K3: formulir (k3/formulir) portrait, tabel lain landscape. Butir tanpa data = partial no-data (garis merah), bukan baris "Belum ada data" di tabel. Ubah layout → naikkan BODY_VERSION controller.

## Laporan Pembangkit signers & workflow come from ReportWorkflowService
Signers of the 5 Laporan Pembangkit (operasi, har, k3, logistik, pdm) are resolved ONLY by ReportSignatories::holder(unit, EmployeePosition) via employees.singleton_key — never LIKE on position, never hardcoded names. Pengesahan = Koordinator Pemeliharaan → TL Pemeliharaan → Manager UL (printed Mengetahui·Menyetujui·Memeriksa); in-report block = Project Leader + Office {divisi}, PdM = Koordinator Pemeliharaan + PIC PDM (ReportModule::reportSigners). Blades print them via signature_blocks (tables id="ttd-pengesahan"/"ttd-laporan", no nested tables) and controllers refresh them in saved HTML with withCurrentSignatures(); signature images appear only when status FINAL. Status/transitions/audit live in report_workflows(+_steps, _logs); store/regenerate must call ensureReportEditable(). Signing is authorised by employees.user_id == step employee, verify by report_unit.approve.
