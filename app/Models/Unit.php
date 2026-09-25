<?php

namespace App\Models;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A generating unit (PLTU / PLTD / PLTG / PLTM).
 *
 * @property int $id
 * @property int|null $service_unit_id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property UnitType $type
 * @property string|null $installed_capacity_mw
 * @property string|null $location
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int $attendance_radius_m
 * @property UnitStatus $status
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ServiceUnit|null $serviceUnit
 */
#[Fillable([
    'service_unit_id',
    'code',
    'name',
    'slug',
    'type',
    'installed_capacity_mw',
    'location',
    'latitude',
    'longitude',
    'attendance_radius_m',
    'status',
    'is_active',
])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UnitType::class,
            'status' => UnitStatus::class,
            'is_active' => 'boolean',
            'attendance_radius_m' => 'integer',
        ];
    }

    /** Whether Super Admin has pinned the office coordinates used for absen masuk/pulang. */
    public function hasAttendanceLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * @return BelongsTo<ServiceUnit, $this>
     */
    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class);
    }

    /**
     * @return HasMany<Machine, $this>
     */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * The Manager UL for this generating unit, resolved from its parent service unit or directly.
     */
    public function manager(): ?Employee
    {
        if ($this->service_unit_id !== null && $this->serviceUnit) {
            $suManager = $this->serviceUnit->manager();
            if ($suManager !== null) {
                return $suManager;
            }
        }

        return $this->employees()
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->where('position', 'like', '%manager ul%')
                    ->orWhere('position', 'like', '%manajer ul%')
                    ->orWhere('position', 'like', '%manager%')
                    ->orWhere('position', 'like', '%manajer%');
            })
            ->first();
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Limit the query to the units the given user may see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasGlobalAccess()) {
            return;
        }

        $query->whereIn('id', $user->accessibleUnitIds());
    }
}
