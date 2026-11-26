<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3ActivityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A K3 activity type used by the Time Frame plan (e.g. Inspeksi P3K, Inspeksi
 * Potensi Bahaya Kebakaran). Per-unit lookup, edited via CRUD.
 *
 * @property int $id
 * @property int|null $unit_id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property string|null $default_pic
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'code', 'name', 'category', 'default_pic', 'sort_order', 'is_active'])]
class K3ActivityType extends Model
{
    /** @use HasFactory<K3ActivityTypeFactory> */
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
