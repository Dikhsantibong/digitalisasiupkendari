<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\SecurityPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A security muster/patrol post per unit.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'name', 'sort_order', 'is_active'])]
class SecurityPost extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<SecurityPostFactory> */
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
