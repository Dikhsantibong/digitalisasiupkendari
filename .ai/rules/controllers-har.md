---
paths:
  - 'app/Http/Controllers/Har/**'
---

# Controllers Har

## HAR editable report document reuses the shared dual editor
The HAR monthly report has an editable-document flow (Har\DocumentController → har/laporan/document) mirroring Operasi's Berita Acara: generated from HarDocumentBuilder + HarDocumentGridBuilder, edited as rich text (PDF) or spreadsheet grid (Excel), saved to har_document_records (one per unit+type+period), PDF via dompdf from the edited content. Frontend uses the shared components/document/document-editor.tsx (also intended to back the Operasi editor). ISO numbers (FMKD-314-…) default from config('har.document.numbers') and are editable inside the document itself — do NOT auto-generate them. View gated by har.laporan.view; saving by har.input.write (Manager UL is view-only).
