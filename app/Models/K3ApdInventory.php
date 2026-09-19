<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daftar Inventaris Alat Pelindung Diri (APD) per unit, bulan, dan tahun.
 * Dikelompokkan per grup dan subkategori.
 */
#[Fillable([
    'unit_id', 'year', 'month', 'grup', 'subkategori', 'no_urut', 'nama',
    'jumlah', 'satuan', 'lokasi', 'keterangan', 'foto', 'sort_order', 'input_by',
])]
class K3ApdInventory extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_apd_inventories';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'no_urut' => 'integer',
            'jumlah' => 'integer',
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
