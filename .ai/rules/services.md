---
paths:
  - app/Services/Impersonation.php
  - app/Services/UnsafeConditionSync.php
---

# Services

## Pindah Akun (Super Admin impersonation) — security rules
All rules live in App\Services\Impersonation + middleware HandleImpersonation (web, after EnsureUserIsActive). Start: POST admin.impersonation.start/{user} (throttle 10/min) by an active Super Admin, password checked in the same POST unless auth.password_confirmed_at is within auth.password_timeout; never into a Super Admin, an inactive user or oneself, never nested. Session keys `impersonation` {admin_id, started_at, confirmed_at}; session id + CSRF regenerated on each switch; the admin's auth.password_confirmed_at is moved out (restored on stop); no remember cookie; RecordSuccessfulLogin skips while `impersonation_switching`. Middleware: signs out completely if the admin lost the role / was deactivated, auto-returns after MAX_MINUTES (60), blocks profile.update/destroy, user-password.*, two-factor.*, password.confirm*, passkey*, notifications.push.* and the switcher routes. ActivityLogger adds properties.impersonated_by. Back: POST impersonation.stop. Inertia shares auth.impersonator & auth.canImpersonate; UI = ImpersonationBanner + AccountSwitcher in AppLayout (Ctrl+Shift+K, user menu, Kelola Pengguna row). The client never re-links device push while impersonating. Add any new account-security route to BLOCKED.

## Unsafe Action & Condition disimpan per lembar (Tambah Baris + Simpan)
Halaman Unsafe Operasi & HAR tidak memakai modal lagi: komponen shared `components/k3/unsafe-condition-sheet.tsx` POST ke `{operasi|har}.input.unsafe-condition.sync` → `App\Services\UnsafeConditionSync::save` (rows dengan id = update, tanpa id = baru dengan source modul, hanya `deleted_ids` yang dihapus; foto dipertahankan hanya bila path sama dengan milik temuan; notifikasi unsafeFound/unsafeClosed tetap). Route store/update/destroy per-temuan tetap ada (dipakai test & notifikasi) dan punya whereNumber agar `/simpan` tidak tertangkap. Trap: `$request->validate()` mengurutkan rows menurut urutan rule pertama yang mengenai key — ksort rows sebelum dipakai.
