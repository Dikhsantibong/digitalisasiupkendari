<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pencatatan Stand Flow Meter BBM Bulanan Modul Operasi (Akses 2 - Pengusahaan).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $fuel_name
 * @property int $month
 * @property int $year
 * @property array<int, array<string, mixed>>|null $machine_parameters
 * @property array<int, array<string, mixed>>|null $readings
 * @property array<string, mixed>|null $totals
 * @property string|null $catatan
 * @property int|null $input_by
 * @property-read Unit $unit
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'fuel_name',
    'month',
    'year',
    'machine_parameters',
    'readings',
    'totals',
    'catatan',
    'input_by',
])]
class OperasiStandMeter extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_stand_meters';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'month' => 'integer',
            'year' => 'integer',
            'machine_parameters' => 'array',
            'readings' => 'array',
            'totals' => 'array',
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
