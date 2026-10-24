<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Masuk sebagai" — a Super Admin works in the application as another user to
 * check what that user sees, then returns to their own account. The only
 * place that starts, checks and ends it, so the rules hold everywhere:
 *
 * - only an active Super Admin (with a recently confirmed password, enforced
 *   on the route) may start, never while already impersonating;
 * - never into a Super Admin, an inactive account, or oneself;
 * - the session id and CSRF token are regenerated on every switch (no
 *   session fixation), the Super Admin's password confirmation is set aside
 *   so the borrowed session cannot pass `password.confirm`, and no
 *   remember-me cookie is issued for the user;
 * - it ends by itself after {@see self::MAX_MINUTES}, and at once when the
 *   Super Admin loses that role or is deactivated
 *   ({@see HandleImpersonation});
 * - the user's own account settings (password, 2FA, profile, device push)
 *   cannot be changed meanwhile;
 * - every start, end and save made meanwhile is logged with the Super Admin.
 */
class Impersonation
{
    /** Session key: ['admin_id' => int, 'started_at' => int, 'confirmed_at' => int|null]. */
    public const SESSION_KEY = 'impersonation';

    /** Set while switching accounts, so the Login event is not recorded as a real sign in. */
    public const SWITCHING_KEY = 'impersonation_switching';

    public const MAX_MINUTES = 60;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function isActive(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has(self::SESSION_KEY);
    }

    /**
     * The Super Admin behind the current session, while impersonating.
     */
    public function impersonator(Request $request): ?User
    {
        if (! $this->isActive($request)) {
            return null;
        }

        return User::query()->find((int) ($request->session()->get(self::SESSION_KEY)['admin_id'] ?? 0));
    }

    /**
     * Why the admin may not switch to the target, or null when allowed.
     */
    public function refusal(User $admin, User $target, Request $request): ?string
    {
        return match (true) {
            ! $admin->is_active || ! $admin->isSuperAdmin() => 'Hanya Super Admin yang dapat masuk sebagai pengguna lain.',
            $this->isActive($request) => 'Kembali ke akun Super Admin terlebih dahulu.',
            $admin->is($target) => 'Anda sudah masuk dengan akun ini.',
            ! $target->is_active => 'Akun ini nonaktif.',
            $target->isSuperAdmin() => 'Tidak dapat masuk sebagai Super Admin lain.',
            default => null,
        };
    }

    public function start(Request $request, User $admin, User $target): void
    {
        $session = $request->session();

        $this->activityLogger->log(ActivityEvent::Updated, "{$admin->name} masuk sebagai {$target->name} ({$target->email})", $target, ['impersonation' => 'start']);

        $session->put(self::SESSION_KEY, [
            'admin_id' => $admin->id,
            'started_at' => now()->getTimestamp(),
            // The admin's password confirmation must not serve the borrowed session.
            'confirmed_at' => $session->pull('auth.password_confirmed_at'),
        ]);
        $session->put(self::SWITCHING_KEY, true);

        Auth::guard('web')->login($target);

        $session->forget(self::SWITCHING_KEY);
        $session->regenerate();
        $session->regenerateToken();
    }

    /**
     * Back to the Super Admin; false (and fully signed out) when that account
     * may no longer be used.
     */
    public function stop(Request $request, string $reason = 'kembali'): bool
    {
        $session = $request->session();
        $data = (array) $session->get(self::SESSION_KEY, []);
        $admin = User::query()->find((int) ($data['admin_id'] ?? 0));
        $user = $request->user();

        $session->forget(self::SESSION_KEY);

        if ($admin === null || ! $admin->is_active || ! $admin->isSuperAdmin()) {
            $this->signOut($request);

            return false;
        }

        $session->put(self::SWITCHING_KEY, true);
        Auth::guard('web')->login($admin);
        $session->forget(self::SWITCHING_KEY);

        $session->regenerate();
        $session->regenerateToken();
        if (! empty($data['confirmed_at'])) {
            $session->put('auth.password_confirmed_at', $data['confirmed_at']);
        }

        $this->activityLogger->log(ActivityEvent::Updated, "{$admin->name} kembali dari akun ".($user?->name ?? '-')." ({$reason})", $user, ['impersonation' => 'stop']);

        return true;
    }

    public function expired(Request $request): bool
    {
        $startedAt = (int) ($request->session()->get(self::SESSION_KEY)['started_at'] ?? 0);

        return $startedAt === 0 || now()->getTimestamp() - $startedAt > self::MAX_MINUTES * 60;
    }

    /** Minutes left before it ends by itself. */
    public function minutesLeft(Request $request): int
    {
        $startedAt = (int) ($request->session()->get(self::SESSION_KEY)['started_at'] ?? 0);

        return max(0, (int) ceil((self::MAX_MINUTES * 60 - (now()->getTimestamp() - $startedAt)) / 60));
    }

    public function signOut(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
