<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The monthly Pemakaian Bahan Bakar sheet of a unit: liters per jenis BBM ×
 * mesin per tanggal, keyed "{fuelCode}_{machineId}" then by day.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $month
 * @property int $year
 * @property array<string, array<int|string, float>>|null $raw_readings
 * @property array<string, float>|null $totals_by_fuel
 * @property array<int|string, float>|null $totals_by_machine
 * @property string $grand_total_liter
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'month', 'year', 'raw_readings', 'totals_by_fuel', 'totals_by_machine', 'grand_total_liter', 'catatan', 'input_by'])]
class OperasiPemakaianBbm extends Model
{
    use BelongsToUnit;

    protected $table = 'operasi_pemakaian_bbm';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'raw_readings' => 'array',
            'totals_by_fuel' => 'array',
            'totals_by_machine' => 'array',
            'grand_total_liter' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
