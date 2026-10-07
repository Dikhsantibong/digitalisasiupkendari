<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pencatatan Pemakaian Pelumas Bulanan Modul Operasi (Akses 2 - Pengusahaan).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $month
 * @property int $year
 * @property float $grand_total_liter
 * @property array|null $raw_readings the total per cell (tambah + ganti)
 * @property array|null $readings_ganti the pelumas ganti (oil change) part per cell
 * @property array|null $totals_by_lubricant
 * @property array|null $totals_by_machine
 * @property string|null $catatan
 * @property int|null $input_by
 * @property-read Unit $unit
 * @property-read User|null $inputUser
 * @property-read Collection<int, OperasiPemakaianPelumasItem> $items
 */
#[Fillable([
    'unit_id',
    'month',
    'year',
    'grand_total_liter',
    'raw_readings',
    'readings_ganti',
    'totals_by_lubricant',
    'totals_by_machine',
    'catatan',
    'input_by',
])]
class OperasiPemakaianPelumas extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_pemakaian_pelumas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'month' => 'integer',
            'year' => 'integer',
            'grand_total_liter' => 'decimal:2',
            'raw_readings' => 'array',
            'readings_ganti' => 'array',
            'totals_by_lubricant' => 'array',
            'totals_by_machine' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * @return HasMany<OperasiPemakaianPelumasItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OperasiPemakaianPelumasItem::class, 'pemakaian_pelumas_id');
    }
}
