---
paths:
  - 'resources/js/pages/operator/**'
---

# Operator

## Operator pages live in one folder per page
Like har/input and k3/input, every Operator page is resources/js/pages/operator/{slug}/index.tsx (absensi, logsheet, mutasi, presensi), rendered as Inertia::render('operator/{slug}/index'). A new Operator page gets its own folder. Update the controller render name, the phone menu `component` in layouts/mobile/menus/umum.ts and the tests' ->component() together; ensure_pages_exist makes the tests fail if the file is missing.
