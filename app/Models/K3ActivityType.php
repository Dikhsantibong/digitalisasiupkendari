<?php

namespace App\Models;

use Database\Factories\K3ActivityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A K3 activity type used by the Time Frame plan (e.g. Inspeksi P3K, Inspeksi
 * Potensi Bahaya Kebakaran). Global lookup, edited via CRUD.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property string|null $default_pic
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'category', 'default_pic', 'sort_order', 'is_active'])]
class K3ActivityType extends Model
{
    /** @use HasFactory<K3ActivityTypeFactory> */
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
