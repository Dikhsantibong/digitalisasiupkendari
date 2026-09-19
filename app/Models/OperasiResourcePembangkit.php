<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laporan Resource Pembangkit (BBM Pembangkit) Modul HAR.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $tanggal
 * @property string $stok_awal
 * @property string $pemakaian
 * @property string $pengiriman
 * @property string $stok_akhir
 * @property string|null $keterangan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'year',
    'month',
    'tanggal',
    'stok_awal',
    'pemakaian',
    'pengiriman',
    'stok_akhir',
    'keterangan',
    'input_by',
])]
class OperasiResourcePembangkit extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_resource_pembangkits';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'tanggal' => 'integer',
            'stok_awal' => 'decimal:2',
            'pemakaian' => 'decimal:2',
            'pengiriman' => 'decimal:2',
            'stok_akhir' => 'decimal:2',
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
