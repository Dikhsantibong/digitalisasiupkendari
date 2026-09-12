<?php

namespace App\Models;

use Database\Factories\MaintenanceCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A routine maintenance cycle (P1=7D, P2=14D, P4=84D, …). Global lookup.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $interval_days
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'interval_days', 'description', 'sort_order', 'is_active'])]
class MaintenanceCycle extends Model
{
    /** @use HasFactory<MaintenanceCycleFactory> */
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
