<?php

namespace App\Models;

use App\Enums\StockItemType;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PhysicalStockTakeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An end-of-period physical stock count for a tank or lubricant type.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $report_period_id
 * @property StockItemType $item_type
 * @property int|null $tank_id
 * @property int|null $lubricant_type_id
 * @property string|null $physical_qty_liter
 * @property string|null $physical_drum
 * @property string|null $physical_cm
 * @property int|null $input_by
 * @property-read Unit $unit
 * @property-read ReportPeriod $reportPeriod
 * @property-read FuelTank|null $tank
 * @property-read LubricantType|null $lubricantType
 */
#[Fillable([
    'unit_id', 'report_period_id', 'item_type', 'tank_id', 'lubricant_type_id',
    'physical_qty_liter', 'physical_drum', 'physical_cm', 'input_by',
])]
class PhysicalStockTake extends Model
{
    /** @use HasFactory<PhysicalStockTakeFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => StockItemType::class,
        ];
    }

    /**
     * @return BelongsTo<ReportPeriod, $this>
     */
    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportPeriod::class);
    }

    /**
     * @return BelongsTo<FuelTank, $this>
     */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'tank_id');
    }

    /**
     * @return BelongsTo<LubricantType, $this>
     */
    public function lubricantType(): BelongsTo
    {
        return $this->belongsTo(LubricantType::class);
    }
}
