<?php

namespace App\Models;

use Database\Factories\RoleAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Binds a user to a role at a specific place in the organisation. A role with
 * unit scope carries a unit, a role with service unit scope carries a service
 * unit, and a global role carries neither.
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property int|null $service_unit_id
 * @property int|null $unit_id
 * @property int|null $assigned_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role $role
 * @property-read User $user
 */
#[Fillable(['user_id', 'role_id', 'service_unit_id', 'unit_id', 'assigned_by'])]
class RoleAssignment extends Model
{
    /** @use HasFactory<RoleAssignmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<ServiceUnit, $this>
     */
    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * A human readable description of where this assignment applies.
     */
    public function locationLabel(): string
    {
        return $this->unit?->name
            ?? $this->serviceUnit?->name
            ?? 'UP Kendari';
    }
}
