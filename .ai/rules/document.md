---
paths:
  - 'resources/js/components/document/**'
---

# Document

## Report documents on phones: scaled A4 page
DocumentEditor passes `fitPage` to RichTextEditor: on screens narrower than an A4 page (794px) the TinyMCE body is laid out at 794px and scaled with CSS zoom to the editor width (wider jadwal tables scroll inside the editor); desktop is untouched. TinyMCE `mobile` config hides the floating table toolbar. Pratinjau PDF on phones (useIsMobile) shows "Buka Pratinjau PDF / Unduh PDF" instead of the iframe (Android Chrome cannot render PDF inline). Applies to every module's laporan document & pengusahaan page — fix layout issues here, not per page.

## Report documents load in two steps (deferred body, lazy editors)
The laporan document & pengusahaan controllers (Operasi/Har/K3/Logistik/Pdm) return document_number, content, content_styles, letterhead and grid through `DefersReportDocument::deferredDocument()` — one Inertia deferred group `document`, the body built once. DocumentEditor shows a skeleton until those props exist. Text mode opens as `DocumentReadView` (plain iframe); TinyMCE (`rich-text-editor`, ~1.2 MB) and x-data-spreadsheet are `React.lazy` chunks loaded only on "Edit Teks" / Excel. The TinyMCE editor is uncontrolled (`contentRef`): read the HTML with `currentHtml()` on save and on leaving the editor. Save posts with `preserveState` + `only: [workflow, has_saved, format, can_write]` so the document is not rebuilt. Tests: assert those props via `$page->loadDeferredProps('document', ...)`; collect props by reference in a `function () use (&$x)` closure, never inside an arrow fn (captures by value).

## Read view needs its own screen fixes for print-only report CSS
The document page opens in DocumentReadView (a plain iframe, NOT TinyMCE), so rules scoped to `.mce-content-body` (cover bleed fixes etc.) do not apply there. Covers (.op-cover, .har-cover, .k3-cover, .pc-cover) are drawn for dompdf with negative page-bleed margins and a fixed 210mm width: the read view re-centres them as a whole A4 page in its own CSS — add any new cover class there too. contentStyles may contain literal <style>…</style> tags (e.g. k3 pengusahaan-styles): strip them before injecting into the iframe's <style>, or the early "</style>" drops every later rule.
