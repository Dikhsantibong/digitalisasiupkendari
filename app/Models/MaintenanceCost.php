<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Manual monthly cost totals per unit — an override used in place of the
 * per-Work-Order sum when {@see $use_manual} is set.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string|null $service_cost
 * @property string|null $material_cost
 * @property bool $use_manual
 */
#[Fillable(['unit_id', 'year', 'month', 'service_cost', 'material_cost', 'use_manual', 'keterangan', 'input_by'])]
class MaintenanceCost extends Model
{
    use BelongsToUnit;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'use_manual' => 'boolean',
        ];
    }
}
