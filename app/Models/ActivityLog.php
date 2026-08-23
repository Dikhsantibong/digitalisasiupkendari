<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An audit record of something a user did. Rows are append-only; the table has
 * no updated_at column.
 *
 * @property int $id
 * @property int|null $user_id
 * @property ActivityEvent $event
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $service_unit_id
 * @property int|null $unit_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id',
    'event',
    'description',
    'subject_type',
    'subject_id',
    'service_unit_id',
    'unit_id',
    'properties',
    'ip_address',
    'user_agent',
])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<ServiceUnit, $this>
     */
    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Limit the query to activity the given user is allowed to review.
     *
     * Entries not tied to any unit — account and role changes, for example —
     * are only visible to users with global access.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasGlobalAccess()) {
            return;
        }

        $unitIds = $user->accessibleUnitIds();
        $serviceUnitIds = $user->accessibleServiceUnitIds();

        $query->where(function (Builder $query) use ($unitIds, $serviceUnitIds): void {
            $query->whereIn('unit_id', $unitIds)
                ->orWhereIn('service_unit_id', $serviceUnitIds);
        });
    }
}
