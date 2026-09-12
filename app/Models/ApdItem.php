<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\ApdItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A specific APD item per unit, belonging to a global APD category.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $apd_category_id
 * @property string $name
 * @property string|null $location
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'apd_category_id', 'name', 'location', 'sort_order', 'is_active'])]
class ApdItem extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<ApdItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ApdCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ApdCategory::class, 'apd_category_id');
    }
}
