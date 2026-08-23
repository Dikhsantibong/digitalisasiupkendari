<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user === null ? [] : $this->permissions($user),
                'roles' => $user === null ? [] : $this->roles($user),
                'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
                'hasGlobalAccess' => $user?->hasGlobalAccess() ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The permission names the interface may use to show or hide navigation and
     * actions. Server-side policies remain the authority.
     *
     * @return list<string>
     */
    private function permissions(User $user): array
    {
        return app(AccessControl::class)
            ->permissionNames($user)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function roles(User $user): array
    {
        return $user->roles()
            ->get(['roles.id', 'roles.name', 'roles.display_name', 'roles.scope'])
            ->unique('id')
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'display_name' => $role->display_name,
                'scope' => $role->scope->value,
            ])
            ->values()
            ->all();
    }
}
