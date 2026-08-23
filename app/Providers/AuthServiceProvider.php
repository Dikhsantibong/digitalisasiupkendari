<?php

namespace App\Providers;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\AccessControl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the permission catalogue into Laravel's authorisation layer.
 *
 * Two things happen here and nowhere else:
 *   1. Super Admin bypasses every check, so permissions added by future modules
 *      require no re-seeding of that role.
 *   2. Every permission becomes a gate, letting controllers, Blade, and the
 *      Inertia layer ask `$user->can('unit.create')` without a model instance.
 *      Model-bound checks continue to go through policies, which additionally
 *      enforce unit scope.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccessControl::class);
    }

    public function boot(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });

        foreach (PermissionName::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user): bool => $user->hasPermissionTo($permission),
            );
        }
    }
}
