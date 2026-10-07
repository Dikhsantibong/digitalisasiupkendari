<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail item per lubricant × machine for pemakaian pelumas.
 *
 * @property int $id
 * @property int $pemakaian_pelumas_id
 * @property int|null $lubricant_type_id
 * @property string $lubricant_name
 * @property int|null $machine_id
 * @property string $machine_name
 * @property array|null $daily_readings
 * @property float $subtotal_p1
 * @property float $subtotal_p2
 * @property float $subtotal_p3
 * @property float $total_liter
 * @property-read OperasiPemakaianPelumas $pemakaianPelumas
 * @property-read LubricantType|null $lubricantType
 * @property-read Machine|null $machine
 */
#[Fillable([
    'pemakaian_pelumas_id',
    'lubricant_type_id',
    'lubricant_name',
    'machine_id',
    'machine_name',
    'daily_readings',
    'subtotal_p1',
    'subtotal_p2',
    'subtotal_p3',
    'total_liter',
])]
class OperasiPemakaianPelumasItem extends Model
{
    use HasFactory;

    protected $table = 'operasi_pemakaian_pelumas_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_readings' => 'array',
            'subtotal_p1' => 'decimal:2',
            'subtotal_p2' => 'decimal:2',
            'subtotal_p3' => 'decimal:2',
            'total_liter' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OperasiPemakaianPelumas, $this>
     */
    public function pemakaianPelumas(): BelongsTo
    {
        return $this->belongsTo(OperasiPemakaianPelumas::class, 'pemakaian_pelumas_id');
    }

    /**
     * @return BelongsTo<LubricantType, $this>
     */
    public function lubricantType(): BelongsTo
    {
        return $this->belongsTo(LubricantType::class);
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
