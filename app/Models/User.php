<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Services\AccessControl;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $employee_id
 * @property string $email
 * @property string|null $position
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'employee_id', 'email', 'position', 'phone', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_assignments')
            ->withPivot(['service_unit_id', 'unit_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin);
    }

    /**
     * Whether the user holds the given role anywhere in the organisation.
     *
     * Prefer permission checks over this method; it exists for presentation and
     * for the Super Admin gate bypass, not for authorising business actions.
     */
    public function hasRole(RoleName|string $role): bool
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        return $this->accessControl()->roleNames($this)->contains($name);
    }

    /**
     * Whether the user's data visibility spans the whole of UP Kendari.
     */
    public function hasGlobalAccess(): bool
    {
        return $this->accessControl()->hasGlobalAccess($this);
    }

    public function hasPermissionTo(PermissionName|string $permission): bool
    {
        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        return $this->accessControl()->permissionNames($this)->contains($name);
    }

    /**
     * @return list<int>
     */
    public function accessibleUnitIds(): array
    {
        return $this->accessControl()->accessibleUnitIds($this);
    }

    /**
     * @return list<int>
     */
    public function accessibleServiceUnitIds(): array
    {
        return $this->accessControl()->accessibleServiceUnitIds($this);
    }

    public function canAccessUnit(Unit|int|null $unit): bool
    {
        return $this->accessControl()->canAccessUnit($this, $unit);
    }

    public function canAccessServiceUnit(ServiceUnit|int|null $serviceUnit): bool
    {
        return $this->accessControl()->canAccessServiceUnit($this, $serviceUnit);
    }

    /**
     * Give the user a role at a place in the organisation.
     *
     * The scope carried by the assignment is derived from the role itself, so a
     * unit-scoped role can never be stored against a service unit and vice versa.
     *
     * @throws \InvalidArgumentException when the scope does not match the role
     */
    public function assignRole(
        Role|RoleName|string $role,
        ServiceUnit|Unit|null $scope = null,
        ?self $assignedBy = null,
    ): RoleAssignment {
        $role = $this->resolveRole($role);

        $unitId = null;
        $serviceUnitId = null;

        if ($role->scope->requiresUnit()) {
            if (! $scope instanceof Unit) {
                throw new \InvalidArgumentException("Role [{$role->name}] harus ditugaskan pada satu unit pembangkit.");
            }

            $unitId = $scope->getKey();
        } elseif ($role->scope->requiresServiceUnit()) {
            if (! $scope instanceof ServiceUnit) {
                throw new \InvalidArgumentException("Role [{$role->name}] harus ditugaskan pada satu unit layanan.");
            }

            $serviceUnitId = $scope->getKey();
        }

        $assignment = $this->roleAssignments()->firstOrCreate([
            'role_id' => $role->getKey(),
            'service_unit_id' => $serviceUnitId,
            'unit_id' => $unitId,
        ], [
            'assigned_by' => $assignedBy?->getKey(),
        ]);

        $this->forgetAccessCache();

        return $assignment;
    }

    /**
     * Remove a role from the user, optionally only at one place.
     */
    public function revokeRole(Role|RoleName|string $role, ServiceUnit|Unit|null $scope = null): int
    {
        $role = $this->resolveRole($role);

        $query = $this->roleAssignments()->where('role_id', $role->getKey());

        if ($scope instanceof Unit) {
            $query->where('unit_id', $scope->getKey());
        } elseif ($scope instanceof ServiceUnit) {
            $query->where('service_unit_id', $scope->getKey());
        }

        $deleted = $query->delete();

        $this->forgetAccessCache();

        return $deleted;
    }

    /**
     * Drop memoised access data after roles or assignments change.
     */
    public function forgetAccessCache(): void
    {
        $this->accessControl()->forget($this);
    }

    protected function accessControl(): AccessControl
    {
        return app(AccessControl::class);
    }

    private function resolveRole(Role|RoleName|string $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        $name = $role instanceof RoleName ? $role->value : $role;

        return Role::query()->where('name', $name)->firstOrFail();
    }
}
