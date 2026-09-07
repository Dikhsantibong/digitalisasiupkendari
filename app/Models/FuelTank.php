<?php

namespace App\Models;

use App\Enums\TankFuelType;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\FuelTankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-unit fuel storage tank master.
 *
 * @property int $id
 * @property int $unit_id
 * @property string|null $code
 * @property string $name
 * @property TankFuelType $fuel_type
 * @property string|null $capacity_liter
 * @property bool $is_daily_tank
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'code', 'name', 'fuel_type', 'capacity_liter', 'is_daily_tank', 'sort_order', 'is_active'])]
class FuelTank extends Model
{
    /** @use HasFactory<FuelTankFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fuel_type' => TankFuelType::class,
            'is_daily_tank' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
