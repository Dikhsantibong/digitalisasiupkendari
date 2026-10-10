---
paths:
  - 'app/Services/Reports/**'
---

# Reports

## Laporan multi-orientasi: satu section per butir Daftar Isi
Laporan K3 Pembangkit & Operasi Pembangkit: body = satu div per butir Daftar Isi (`.k3-section`/`.op-section`, id `sec-*`), tambah `.k3-landscape`/`.op-landscape` untuk halaman landscape. PDF via `OrientationPdfMerger::renderSections(styles, body, sectionClass, landscapeClass, footer, unnumberedPages: 1)` — mengelompokkan section berurutan per orientasi, merge jadi 1 PDF, sampul tanpa nomor, dan mengisi teks `<a href="#sec-x">…</a>` di Daftar Isi dengan halaman nyata (suffix di luar link, mis. "4.a"). Aturan K3: formulir (k3/formulir) portrait, tabel lain landscape. Butir tanpa data = partial no-data (garis merah), bukan baris "Belum ada data" di tabel. Ubah layout → naikkan BODY_VERSION controller.

## Laporan Pembangkit signers & workflow come from ReportWorkflowService
Signers of the 5 Laporan Pembangkit (operasi, har, k3, logistik, pdm) are resolved ONLY by ReportSignatories::holder(unit, EmployeePosition) via employees.singleton_key — never LIKE on position, never hardcoded names. Pengesahan = Koordinator Pemeliharaan → TL Pemeliharaan → Manager UL (printed Mengetahui·Menyetujui·Memeriksa); in-report block = Project Leader + Office {divisi}, PdM = Koordinator Pemeliharaan + PIC PDM (ReportModule::reportSigners). Blades print them via signature_blocks (tables id="ttd-pengesahan"/"ttd-laporan", no nested tables) and controllers refresh them in saved HTML with withCurrentSignatures(); signature images appear only when status FINAL. Status/transitions/audit live in report_workflows(+_steps, _logs); store/regenerate must call ensureReportEditable(). Signing is authorised by employees.user_id == step employee, verify by report_unit.approve.

## Penanda tangan lintas unit (report_signer_delegations)
Who signs a Laporan Pembangkit is resolved ONLY by ReportSignatories::holder(unit, jabatan). A Super Admin setting (Administrasi → Penanda Tangan Laporan, admin.report-signers.*, ReportSignerDelegation) can delegate a unit's jabatan to the holder of the same jabatan at another unit (one level, no chains), e.g. TL Pemeliharaan PLTD Poasia also signs PLTD Poasia Containerized. Use holder() (delegation-aware) for signers; ownHolder() only for "the unit's own employee". SignerDelegations::save may grant the signer's unit-scoped roles at the delegated unit (stored in granted_assignment_ids) and remove() revokes only those. Workflow steps freeze the employee at ajukan, so a change applies to reports diajukan afterwards.

## Laporan Pengusahaan go through the same workflow (no Koordinator)
`ReportModule` also has `operasi-pengusahaan`, `har-pengusahaan`, `k3-pengusahaan` (`isPengusahaan()`, `base()`): diajukan by the divisi's Staf (`*.pengusahaan.write`), Team Leader menyetujui, Manager UL mengesahkan — no Koordinator, no Project Leader / Office signers (`reportSigners()` = []). Signers carry an explicit `sequence` (TL = 2, Manager = 3), so after ajukan the status is `awaitingStep(first sequence)` = VERIFIKASI, shown as "Diajukan — Menunggu Persetujuan Team Leader" via `ReportModule::statusLabel()` — always use that, never `$status->label()`, when showing a workflow status. Document / PDF links: `ReportModule::documentUrl()` / `pdfUrl()`. Nobody acts on the first step of a report they submitted themselves.
