---
paths:
  - resources/js/components/app-sidebar.tsx
---

# Js Components

## Sidebar Akses 2 menus carry the divisi name
Keputusan user (2026-11-18): in the sidebar the Akses 2 (non-Project) menus are named after the divisi — "Input Operasi", "Input Pemeliharaan", "Formulir Pemeliharaan", "Input K3 & Keamanan", "Formulir K3 & Keamanan" — built in pengusahaanItems() as `${section} ${module.title}`; a section with its own module name (Operasi "Berita Acara") stays as is. Akses 1 stays "Input Project" / "Formulir Project" (projectTitle). Logistik & PdM have one Input only and keep "Input". IK Logistik & PdM (logistik/pdm.input.instruksi-kerja) reuse BaseInstruksiKerjaController with approvedBy() = TL Pemeliharaan.

## Long menus become dropdown groups
Keputusan user (2026-11-19): the Super Admin and any role whose flat menu would exceed LONG_MENU_ROWS (18 rows = group headings + items + sub-items, see app-sidebar.tsx `collapsibleGroups`) get NavCollapsible groups (except "Umum"; the group of the current page opens); short menus stay flat NavMain. Keep new groups counted by the same menuRows reduce.
