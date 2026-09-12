<?php

namespace App\Models;

use Database\Factories\MaintenanceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A maintenance type (PM, PdM, CM, FLM, ENJI). Global lookup, edited via CRUD.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'category', 'sort_order', 'is_active'])]
class MaintenanceType extends Model
{
    /** @use HasFactory<MaintenanceTypeFactory> */
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
