<?php

namespace App\Models;

use Database\Factories\SrCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Service Request category (CM, FLM, CANCEL, PDM). Global lookup.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'sort_order', 'is_active'])]
class SrCategory extends Model
{
    /** @use HasFactory<SrCategoryFactory> */
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
