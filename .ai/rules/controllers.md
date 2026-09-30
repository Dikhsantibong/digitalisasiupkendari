---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Instruksi Kerja input is shared across modules
IK Pemeliharaan (har_instruksi_kerjas) and IK Operasi (operasi_instruksi_kerjas) share App\Http\Controllers\BaseInstruksiKerjaController (validation, store/destroy/pdf, present, signatories). The PDF/document view is resources/views/har/instruksi-kerja/*. The React editor is components/instruksi-kerja/editor-page.tsx. A module only adds a model + migration (same columns), templates (App\Support\{Har,Operasi}IkTemplates, parts built with HarIkTemplates::section), a subclass controller (model, page, route prefix, permissions, preparedBy jabatan/position) and a thin page passing routes + copy. Routes are {module}.input.instruksi-kerja.{index,store,pdf,destroy}; destroy takes a numeric id. Change the editor/preview and document.blade.php together.
