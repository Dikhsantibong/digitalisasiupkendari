<?php

namespace App\Models;

use App\Enums\TankFuelType;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\FuelReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A fuel delivery from a supplier (manual input).
 *
 * @property int $id
 * @property int $unit_id
 * @property Carbon $report_date
 * @property TankFuelType $fuel_type
 * @property string|null $supplier
 * @property string|null $do_number
 * @property Carbon|null $unloading_date
 * @property string $volume_liter
 * @property string|null $calorie_value
 * @property string|null $price_per_liter
 * @property string|null $transport_cost
 * @property string|null $surveyor_cost
 * @property string|null $keterangan
 * @property int|null $input_by
 * @property-read Unit $unit
 */
#[Fillable([
    'unit_id', 'report_date', 'fuel_type', 'supplier', 'do_number', 'unloading_date',
    'volume_liter', 'calorie_value', 'price_per_liter', 'transport_cost',
    'surveyor_cost', 'keterangan', 'input_by',
])]
class FuelReceipt extends Model
{
    /** @use HasFactory<FuelReceiptFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'unloading_date' => 'date',
            'fuel_type' => TankFuelType::class,
        ];
    }
}
