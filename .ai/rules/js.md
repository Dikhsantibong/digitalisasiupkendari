---
paths:
  - 'resources/js/**'
---

# Js

## Regenerate Wayfinder with --with-form
The Vite wayfinder plugin runs with `formVariants: true` (vite.config.ts), so pages use `Controller.action.form(...)`. If you regenerate from the CLI, you MUST pass `php artisan wayfinder:generate --with-form`; the plain command drops the `.form()` variant and breaks tsc across every page. Prefer letting the dev server/build regenerate; only use the CLI with the flag.
