<?php

namespace App\Models;

use Database\Factories\BbmTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A fuel (BBM) type — HSD, B30, B40, MFO. Global reference master.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'category', 'sort_order', 'is_active'])]
class BbmType extends Model
{
    /** @use HasFactory<BbmTypeFactory> */
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
