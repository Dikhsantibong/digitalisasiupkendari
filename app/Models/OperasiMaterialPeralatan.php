<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laporan Material dan Peralatan (Modul HAR).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $kategori
 * @property string|null $kode_material
 * @property string|null $stok_code
 * @property string $nama_item
 * @property string $stok_awal
 * @property string $masuk
 * @property string $keluar
 * @property string $stok_akhir
 * @property string|null $satuan
 * @property string $harga_satuan
 * @property string $pemakaian_rata_rata
 * @property string $safety_stock
 * @property string $ilt
 * @property string $rop
 * @property string $roq
 * @property int $sort_order
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
    'kategori',
    'kode_material',
    'stok_code',
    'nama_item',
    'stok_awal',
    'masuk',
    'keluar',
    'stok_akhir',
    'satuan',
    'harga_satuan',
    'pemakaian_rata_rata',
    'safety_stock',
    'ilt',
    'rop',
    'roq',
    'sort_order',
    'input_by',
])]
class OperasiMaterialPeralatan extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_material_peralatans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'stok_awal' => 'decimal:2',
            'masuk' => 'decimal:2',
            'keluar' => 'decimal:2',
            'stok_akhir' => 'decimal:2',
            'harga_satuan' => 'decimal:2',
            'pemakaian_rata_rata' => 'decimal:2',
            'safety_stock' => 'decimal:2',
            'ilt' => 'decimal:2',
            'rop' => 'decimal:2',
            'roq' => 'decimal:2',
            'sort_order' => 'integer',
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
