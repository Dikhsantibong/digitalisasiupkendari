<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\AuxiliarySourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-unit auxiliary / standby source master.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'name', 'description', 'sort_order', 'is_active'])]
class AuxiliarySource extends Model
{
    /** @use HasFactory<AuxiliarySourceFactory> */
    use BelongsToUnit, HasFactory;

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
