---
paths:
  - 'app/Http/Controllers/Har/**'
---

# Controllers Har

## HAR editable report document reuses the shared dual editor
The HAR monthly report has an editable-document flow (Har\DocumentController → har/laporan/document) mirroring Operasi's Berita Acara: generated from HarDocumentBuilder + HarDocumentGridBuilder, edited as rich text (PDF) or spreadsheet grid (Excel), saved to har_document_records (one per unit+type+period), PDF via dompdf from the edited content. Frontend uses the shared components/document/document-editor.tsx (also intended to back the Operasi editor). ISO numbers (FMKD-314-…) default from config('har.document.numbers') and are editable inside the document itself — do NOT auto-generate them. View gated by har.laporan.view; saving by har.input.write (Manager UL is view-only).

## Harmes/Harlist hanya lewat har.lapangan.input ke 5 halaman lapangan
Role `harmes`/`harlist` (divisi Pemeliharaan, di bawah Koordinator Pemeliharaan; akun & pegawai per unit dari EmployeeSeeder/DemoAccountSeeder, NIP suffix 25–29) TIDAK memegang har.input.*; mereka pegang `har.lapangan.input` yang hanya membuka: abnormal-gangguan (HarTabel::fieldInput()=true), patrol-check-pemeliharaan (HarLembar::fieldInput()=true), patrol-check-parameter, program-5s5r, unsafe-condition. Gate lewat trait `Concerns\AuthorizesHarInput` (canViewHarInput/canWriteHarInput($user, $fieldPage)). Halaman HAR lapangan baru → set fieldInput() atau pakai trait, JANGAN beri har.input.* ke Harmes/Harlist. Data masuk ke tabel HAR yang sama. Operator (ber-regu, termasuk Leader Shift) = divisi operasi via Employee::fieldDivision(); Harmes/Harlist ikut roster Non Shift Jadwal Shift.

## One Work Order input; WO groups are views, not separate inputs
WO PM/PdM/CM/ENJI (by maintenance_type) and WO Waiting Shutdown / Material & Jasa (by waiting_reason) are all rows of the single work_orders table, entered in one input (har/input/work-order), which has group tabs. The groupings overlap, e.g. a CM WO can also be waiting material, so do NOT split them into separate inputs or tables: that double-counts Maintenance Summary, Rekap Task and cost. A17 material/jasa lines live in work_orders.materials (JSON, one report row per line). The bidang comes from the work group code or name (ELEC → Listrik, INST/I&C → Kontrol & Instrumen, CIV/SIPIL → Sipil, otherwise Mekanik).

## HAR technical formulir are Akses 2 on HarFormulirController
The 13 technical formulir are Pengusahaan pages, not Akses 1: Prelube, Hydrotest, Timing, Crankshaft, Counter Weight, Axial Conrod, Clearance, Combustion, Injector, Motor Current, Vibration, Lube, Battery. Routes: har.pengusahaan.{slug}.* (loop in routes/har.php). Pages: pengusahaan/har/{slug} via components/pengusahaan/har-formulir.tsx. Each controller extends HarFormulirController and only declares slug, model, builder, rules and defaults. Access is har.pengusahaan.* OR the Harmes/Harlist lapangan permission. Records are keyed by unit + machine + test_date. The kop is static (builder defaults, set on first save). Signatories are re-resolved on every save from the unit's Manager UL, Team Leader Pemeliharaan and Staf Pemeliharaan. Do not add page settings, signature inputs or free-HTML editing back. Daily Meeting, Logbook Mutasi and LH-05 stay Akses 1 (har/formulir). Work Order and Service Request are one data source for both accesses: they accept har.input.* OR har.pengusahaan.*.
