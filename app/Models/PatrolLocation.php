<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PatrolLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A security patrol checkpoint per unit (POA1..POA14 at Poasia).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'code', 'name', 'sort_order', 'is_active'])]
class PatrolLocation extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<PatrolLocationFactory> */
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
