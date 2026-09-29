---
paths:
  - 'resources/js/pages/k3/input/**'
---

# Input

## K3 input pages: one folder per route slug
As in har/input, every K3 input page is resources/js/pages/k3/input/{route-slug}/index.tsx, and its controller renders 'k3/input/{route-slug}/index'. The folder is named after the URL slug (accident, apar-check, attachment, certificate, inspection, patrol, …), not a plural. The hub stays at k3/input/index.tsx. When adding or renaming a page, also update its component string in layouts/mobile/menus/k3-project.ts and in the tests' ->component() assertions; route names and URLs are independent of the folder.
