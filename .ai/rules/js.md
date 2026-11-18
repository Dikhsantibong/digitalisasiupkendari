---
paths:
  - 'resources/js/**'
---

# Js

## Regenerate Wayfinder with --with-form
The Vite wayfinder plugin runs with `formVariants: true` (vite.config.ts), so pages use `Controller.action.form(...)`. If you regenerate from the CLI, you MUST pass `php artisan wayfinder:generate --with-form`; the plain command drops the `.form()` variant and breaks tsc across every page. Prefer letting the dev server/build regenerate; only use the CLI with the flag.

## Desktop pages follow design.md (flat, 6px radius, tokens)
Desktop pages: no gradients/blur/glow, no shadow on cards, radius `rounded-md` (not xl/2xl), colours from tokens (bg-card, border-border, text-foreground, primary) not hex, one accent (PLN blue / chart-1) — amber/emerald/red only for semantic status. Use `PageHeader`, `SummaryCard`, `StatusBadge`, `EmptyState`; filter areas use `bg-secondary`; text ≥ 12px. The phone shells (layouts/mobile, components/mobile, `compact` branches) intentionally keep the user-approved mobile style (rounded-xl tiles, pastel icons).

## Never show the word "Pengusahaan" to users
Keputusan user (2026-11-17): the Akses 2 (TL & Staf) menus, pages, reports, PDF/Excel and DB display texts carry the divisi name only — "Laporan Operasi", "Laporan Pemeliharaan", "Laporan K3 & KAM", "Input Operasi", "Formulir Pemeliharaan", "Operasi · kWh". Akses 1 (Laporan Project) is named with "Project" ("Laporan Project Operasi"; in the sidebar Input/Formulir get " Project" via projectTitle() when the account also holds the divisi's Akses 2). Technical identifiers keep "pengusahaan" (routes, permissions *.pengusahaan.*, document type 'pengusahaan', class names) — never rename those. Migration 2026_11_17_103106 fixed stored permission names, role descriptions and saved documents.
