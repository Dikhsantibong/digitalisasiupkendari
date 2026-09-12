<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\P3kBoxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A first-aid (P3K) box per unit, used by the periodic P3K inspections.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $code
 * @property string|null $location
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'code', 'location', 'sort_order', 'is_active'])]
class P3kBox extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<P3kBoxFactory> */
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
}
