<?php

namespace App\Models;

use Database\Factories\ApdCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A category of personal protective equipment (APD). Global lookup.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'sort_order', 'is_active'])]
class ApdCategory extends Model
{
    /** @use HasFactory<ApdCategoryFactory> */
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
