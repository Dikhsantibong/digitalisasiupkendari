<?php

namespace App\Models\Concerns;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared behaviour for every operasi record scoped to a generating unit: the
 * owning unit relation and the {@see scopeVisibleTo} filter that limits queries
 * to the units a user may reach. Scope is resolved only by AccessControl (via
 * the user helpers), never re-derived here.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToUnit
{
    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Limit the query to the records whose unit the given user may see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasGlobalAccess()) {
            return;
        }

        $query->whereIn($this->qualifyColumn('unit_id'), $user->accessibleUnitIds());
    }
}
