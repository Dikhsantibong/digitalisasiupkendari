<?php

namespace App\Models;

use Database\Factories\WoStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Work Order status (APPR, CLOSE, WAPPR, INPRG, …). `is_closed` marks the
 * statuses that count a WO as complete. Global lookup.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_closed
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'is_closed', 'sort_order', 'is_active'])]
class WoStatus extends Model
{
    /** @use HasFactory<WoStatusFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
