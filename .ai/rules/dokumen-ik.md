---
paths:
  - 'resources/js/components/instruksi-kerja/**, resources/js/pages/k3/input/dokumen-ik/**'
---

# Dokumen Ik

## IK editors are visual: edit in place on one A4 sheet
Keputusan user (2026-11-18): every Instruksi Kerja editor (K3 Dokumen IK, and the shared Pemeliharaan/Operasi IK in components/instruksi-kerja/editor-page.tsx) has NO separate form + preview: the user types straight on one A4 portrait sheet (210 × 297 mm with the PDF margins: K3 14 mm, MKP IK 16 × 18 mm) that mirrors the print blade (k3/dokumen-ik/document.blade.php, har/instruksi-kerja/document.blade.php — change them together). Shared pieces live in components/instruksi-kerja/visual-editor.tsx (A4Sheet, AutoText, EDITABLE, PART_FRAME/PART_TOOLS, ToolButton, useFieldFocus, useSaveShortcut, useUnsavedWarning, EditorTips). Keys: Enter = new point (split), Backspace at start = join/outdent, paste lines = points, Tab/Shift+Tab = sub-point (MKP IK), Ctrl+B = **bold**, Ctrl+S = save. The payload sent to store stays the same as before (sections → butir), so backend/PDF are unchanged.
