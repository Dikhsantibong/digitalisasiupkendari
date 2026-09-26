---
paths:
  - 'app/Http/Controllers/Har/**'
---

# Controllers Har

## HAR editable report document reuses the shared dual editor
The HAR monthly report has an editable-document flow (Har\DocumentController → har/laporan/document) mirroring Operasi's Berita Acara: generated from HarDocumentBuilder + HarDocumentGridBuilder, edited as rich text (PDF) or spreadsheet grid (Excel), saved to har_document_records (one per unit+type+period), PDF via dompdf from the edited content. Frontend uses the shared components/document/document-editor.tsx (also intended to back the Operasi editor). ISO numbers (FMKD-314-…) default from config('har.document.numbers') and are editable inside the document itself — do NOT auto-generate them. View gated by har.laporan.view; saving by har.input.write (Manager UL is view-only).

## Harmes/Harlist hanya lewat har.lapangan.input ke 5 halaman lapangan
Role `harmes`/`harlist` (divisi Pemeliharaan, di bawah Koordinator Pemeliharaan; akun & pegawai per unit dari EmployeeSeeder/DemoAccountSeeder, NIP suffix 25–29) TIDAK memegang har.input.*; mereka pegang `har.lapangan.input` yang hanya membuka: abnormal-gangguan (HarTabel::fieldInput()=true), patrol-check-pemeliharaan (HarLembar::fieldInput()=true), patrol-check-parameter, program-5s5r, unsafe-condition. Gate lewat trait `Concerns\AuthorizesHarInput` (canViewHarInput/canWriteHarInput($user, $fieldPage)). Halaman HAR lapangan baru → set fieldInput() atau pakai trait, JANGAN beri har.input.* ke Harmes/Harlist. Data masuk ke tabel HAR yang sama. Operator (ber-regu, termasuk Leader Shift) = divisi operasi via Employee::fieldDivision(); Harmes/Harlist ikut roster Non Shift Jadwal Shift.
