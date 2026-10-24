<?php

namespace App\Http\Middleware;

use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a "Masuk sebagai" session on every request ({@see Impersonation}):
 * ends it when the Super Admin behind it lost the role or was deactivated
 * (signed out completely) or after the time limit (back to the Super Admin),
 * and refuses changes to the borrowed account's own settings — password,
 * two-factor, profile & account deletion, password confirmation and the
 * device push link.
 */
class HandleImpersonation
{
    /** Route-name prefixes closed while impersonating. */
    private const BLOCKED = [
        'profile.update',
        'profile.destroy',
        'user-password.',
        'two-factor.',
        'password.confirm',
        'password.confirmation',
        'passkey',
        'notifications.push.',
        'admin.impersonation.start',
        'admin.impersonation.users',
    ];

    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->isActive($request)) {
            return $next($request);
        }

        // The session expired or was signed out meanwhile: nothing left to guard.
        if ($request->user() === null) {
            $request->session()->forget(Impersonation::SESSION_KEY);

            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if ($routeName === 'impersonation.stop' || $routeName === 'logout') {
            return $next($request);
        }

        $admin = $this->impersonation->impersonator($request);
        if ($admin === null || ! $admin->is_active || ! $admin->isSuperAdmin()) {
            $this->impersonation->signOut($request);

            return redirect()->route('login');
        }

        if ($this->impersonation->expired($request)) {
            $this->impersonation->stop($request, 'batas waktu habis');
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Sesi "masuk sebagai" berakhir otomatis setelah '.Impersonation::MAX_MINUTES.' menit.']);

            return redirect()->route('admin.users.index');
        }

        foreach (self::BLOCKED as $prefix) {
            if ($routeName !== '' && str_starts_with($routeName, $prefix)) {
                abort(403, 'Tidak tersedia saat Anda masuk sebagai pengguna lain. Kembali ke akun Super Admin terlebih dahulu.');
            }
        }

        return $next($request);
    }
}
