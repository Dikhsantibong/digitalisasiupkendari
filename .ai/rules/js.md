---
paths:
  - 'resources/js/**'
---

# Js

## Regenerate Wayfinder with --with-form
The Vite wayfinder plugin runs with `formVariants: true` (vite.config.ts), so pages use `Controller.action.form(...)`. If you regenerate from the CLI, you MUST pass `php artisan wayfinder:generate --with-form`; the plain command drops the `.form()` variant and breaks tsc across every page. Prefer letting the dev server/build regenerate; only use the CLI with the flag.

## Desktop pages follow design.md (flat, 6px radius, tokens)
Desktop pages: no gradients/blur/glow, no shadow on cards, radius `rounded-md` (not xl/2xl), colours from tokens (bg-card, border-border, text-foreground, primary) not hex, one accent (PLN blue / chart-1) — amber/emerald/red only for semantic status. Use `PageHeader`, `SummaryCard`, `StatusBadge`, `EmptyState`; filter areas use `bg-secondary`; text ≥ 12px. The phone shells (layouts/mobile, components/mobile, `compact` branches) intentionally keep the user-approved mobile style (rounded-xl tiles, pastel icons).
