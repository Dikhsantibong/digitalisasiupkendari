<?php

namespace App\Models;

use App\Enums\StatusCodeCategory;
use Database\Factories\UnitStatusCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Star-Stop status code. Global when {@see $unit_id} is null, otherwise a
 * per-unit override.
 *
 * @property int $id
 * @property int|null $unit_id
 * @property string $code
 * @property string $label
 * @property StatusCodeCategory $category
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit|null $unit
 */
#[Fillable(['unit_id', 'code', 'label', 'category', 'is_active'])]
class UnitStatusCode extends Model
{
    /** @use HasFactory<UnitStatusCodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => StatusCodeCategory::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The codes available to a unit: its own overrides plus the global codes.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForUnit(Builder $query, int $unitId): void
    {
        $query->where(function (Builder $query) use ($unitId): void {
            $query->whereNull('unit_id')->orWhere('unit_id', $unitId);
        });
    }
}
