<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * View-only accounts (portal.readonly — kantor induk UP Kendari) may read and
 * download everything their roles open, but no request of theirs may change
 * data: every non-read request is refused here, whatever a page offers,
 * except their own session, profile and notifications.
 */
class EnsureReadOnlyAccess
{
    /** Route-name prefixes a view-only account may still post to (its own account only). */
    private const ALLOWED = [
        'logout',
        'notifications.',
        'profile.',
        'user-password.',
        'two-factor.',
        'password.',
        'passkey',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $request->isMethodSafe() && $user->isReadOnly() && ! $this->allowed($request)) {
            abort(403, 'Akun ini hanya dapat melihat dan mengunduh data — tidak dapat menyimpan, mengubah, atau menghapus.');
        }

        return $next($request);
    }

    private function allowed(Request $request): bool
    {
        $name = (string) $request->route()?->getName();

        foreach (self::ALLOWED as $prefix) {
            if ($name !== '' && str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
