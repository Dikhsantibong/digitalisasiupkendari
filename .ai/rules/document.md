---
paths:
  - 'resources/js/components/document/**'
---

# Document

## Report documents on phones: scaled A4 page
DocumentEditor passes `fitPage` to RichTextEditor: on screens narrower than an A4 page (794px) the TinyMCE body is laid out at 794px and scaled with CSS zoom to the editor width (wider jadwal tables scroll inside the editor); desktop is untouched. TinyMCE `mobile` config hides the floating table toolbar. Pratinjau PDF on phones (useIsMobile) shows "Buka Pratinjau PDF / Unduh PDF" instead of the iframe (Android Chrome cannot render PDF inline). Applies to every module's laporan document & pengusahaan page — fix layout issues here, not per page.
