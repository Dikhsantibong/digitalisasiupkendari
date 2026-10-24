<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Pindah Akun (Super Admin): find a user and work as them, then come back
 * ({@see Impersonation} holds every rule). Starting needs the Super Admin's
 * password unless it was confirmed within auth.password_timeout (checked in
 * the same POST — no GET that a link could trigger), and is rate limited; the
 * search only lists the accounts one may switch to.
 */
class ImpersonationController extends Controller
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * Accounts to switch to, for the quick switcher: active, not a Super
     * Admin, not oneself — name, e-mail and roles only.
     */
    public function users(Request $request): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin->isSuperAdmin() && ! $this->impersonation->isActive($request), 403);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 80);

        $users = User::query()
            ->where('is_active', true)
            ->whereKeyNot($admin->id)
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('roles.name', RoleName::SuperAdmin->value))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->with('roles:id,display_name')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'password_confirmed' => $this->passwordConfirmed($request),
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('display_name')->unique()->values()->all(),
            ])->all(),
        ]);
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        $refusal = $this->impersonation->refusal($admin, $user, $request);
        abort_if($refusal !== null, 403, $refusal ?? '');

        if (! $this->passwordConfirmed($request)) {
            $request->validate(['password' => ['required', 'string', 'current_password:web']], [
                'password.current_password' => 'Kata sandi Super Admin salah.',
            ]);
            $request->session()->put('auth.password_confirmed_at', now()->getTimestamp());
        }

        $this->impersonation->start($request, $admin, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Anda sekarang masuk sebagai {$user->name}. Batas waktu ".Impersonation::MAX_MINUTES.' menit.']);

        return redirect()->route('dashboard');
    }

    private function passwordConfirmed(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return $confirmedAt > 0 && now()->getTimestamp() - $confirmedAt < (int) config('auth.password_timeout', 10800);
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless($this->impersonation->isActive($request), 403);

        if (! $this->impersonation->stop($request)) {
            return redirect()->route('login');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kembali ke akun Super Admin.']);

        return redirect()->route('admin.users.index');
    }
}
