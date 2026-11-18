---
paths:
  - 'resources/js/components/instruksi-kerja/**, resources/js/pages/k3/input/dokumen-ik/**, app/Http/Controllers/BaseInstruksiKerjaController.php, app/Http/Controllers/K3/DokumenIkController.php'
---

# Http Controllers K3

## IK editors have a "Pratinjau PDF" tab rendering the real PDF
Keputusan user (2026-11-18): every IK editor (HAR, Operasi, Logistik, PdM via editor-page.tsx; K3 Dokumen IK) has tabs "Edit Dokumen" / "Pratinjau PDF" (EditorViewTabs + PdfPreview in visual-editor.tsx). The preview shows the server PDF in the browser viewer: the saved PDF (GET {prefix}.pdf) when nothing changed or the account is read-only, otherwise the unsaved draft via POST {prefix}.preview (route 'input/…/pratinjau'; fetch with X-XSRF-TOKEN, Accept json so validation answers 422 with the message). preview() validates with the same validateDocument()/attributes() as store and renders the same pdf view — never stores. A new IK module only adds the preview route and passes routes.preview.
