<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\RoleScope;
use App\Models\Permission;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single place where "what may this user see and do" is resolved.
 *
 * Every module asks this service rather than re-deriving scope rules, so adding
 * a module never means re-implementing access logic. Results are memoised for
 * the lifetime of the request.
 */
class AccessControl
{
    /** @var array<int, Collection<int, string>> */
    private array $roleNames = [];

    /** @var array<int, Collection<int, string>> */
    private array $permissionNames = [];

    /** @var array<int, list<int>> */
    private array $unitIds = [];

    /** @var array<int, list<int>> */
    private array $serviceUnitIds = [];

    /** @var array<int, bool> */
    private array $globalAccess = [];

    /**
     * The distinct role names held by the user across all assignments.
     *
     * @return Collection<int, string>
     */
    public function roleNames(User $user): Collection
    {
        return $this->roleNames[$user->getKey()] ??= $user->roles()
            ->pluck('roles.name')
            ->unique()
            ->values();
    }

    /**
     * The distinct permission names granted by all of the user's roles.
     *
     * Super Admin receives the full catalogue so that permissions added later
     * need no re-seeding of the role.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(User $user): Collection
    {
        return $this->permissionNames[$user->getKey()] ??= $this->resolvePermissionNames($user);
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->roleNames($user)->contains(RoleName::SuperAdmin->value);
    }

    /**
     * Whether any of the user's roles grants organisation-wide visibility.
     */
    public function hasGlobalAccess(User $user): bool
    {
        return $this->globalAccess[$user->getKey()] ??= $user->roles()
            ->where('roles.scope', RoleScope::Global->value)
            ->exists();
    }

    /**
     * Every generating unit the user may reach, directly or through a UL.
     *
     * @return list<int>
     */
    public function accessibleUnitIds(User $user): array
    {
        return $this->unitIds[$user->getKey()] ??= $this->resolveUnitIds($user);
    }

    /**
     * Every service unit the user may reach, including the parents of the units
     * they are assigned to.
     *
     * @return list<int>
     */
    public function accessibleServiceUnitIds(User $user): array
    {
        return $this->serviceUnitIds[$user->getKey()] ??= $this->resolveServiceUnitIds($user);
    }

    public function canAccessUnit(User $user, Unit|int|null $unit): bool
    {
        if ($unit === null) {
            return $this->hasGlobalAccess($user);
        }

        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $unitId = $unit instanceof Unit ? $unit->getKey() : $unit;

        return in_array($unitId, $this->accessibleUnitIds($user), strict: true);
    }

    public function canAccessServiceUnit(User $user, ServiceUnit|int|null $serviceUnit): bool
    {
        if ($serviceUnit === null) {
            return $this->hasGlobalAccess($user);
        }

        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $serviceUnitId = $serviceUnit instanceof ServiceUnit ? $serviceUnit->getKey() : $serviceUnit;

        return in_array($serviceUnitId, $this->accessibleServiceUnitIds($user), strict: true);
    }

    /**
     * Forget everything memoised for a user, after their access has changed.
     */
    public function forget(User $user): void
    {
        $key = $user->getKey();

        unset(
            $this->roleNames[$key],
            $this->permissionNames[$key],
            $this->unitIds[$key],
            $this->serviceUnitIds[$key],
            $this->globalAccess[$key],
        );
    }

    public function flush(): void
    {
        $this->roleNames = [];
        $this->permissionNames = [];
        $this->unitIds = [];
        $this->serviceUnitIds = [];
        $this->globalAccess = [];
    }

    /**
     * @return Collection<int, string>
     */
    private function resolvePermissionNames(User $user): Collection
    {
        $roleIds = $user->roles()->pluck('roles.id')->unique();

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->pluck('name')
            ->unique()
            ->values();
    }

    /**
     * @return list<int>
     */
    private function resolveUnitIds(User $user): array
    {
        if ($this->hasGlobalAccess($user)) {
            return Unit::query()->pluck('id')->all();
        }

        $assignments = $user->roleAssignments()->get(['unit_id', 'service_unit_id']);

        $directUnitIds = $assignments->pluck('unit_id')->filter()->unique();
        $serviceUnitIds = $assignments->pluck('service_unit_id')->filter()->unique();

        $inheritedUnitIds = $serviceUnitIds->isEmpty()
            ? collect()
            : Unit::query()->whereIn('service_unit_id', $serviceUnitIds)->pluck('id');

        return $directUnitIds->merge($inheritedUnitIds)
            ->unique()
            ->values()
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function resolveServiceUnitIds(User $user): array
    {
        if ($this->hasGlobalAccess($user)) {
            return ServiceUnit::query()->pluck('id')->all();
        }

        $assignments = $user->roleAssignments()->get(['unit_id', 'service_unit_id']);

        $directIds = $assignments->pluck('service_unit_id')->filter()->unique();

        $unitIds = $assignments->pluck('unit_id')->filter()->unique();

        $parentIds = $unitIds->isEmpty()
            ? collect()
            : Unit::query()->whereIn('id', $unitIds)->pluck('service_unit_id')->filter();

        return $directIds->merge($parentIds)
            ->unique()
            ->values()
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }
}
