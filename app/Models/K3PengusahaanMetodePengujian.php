<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanMetodePengujianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanMetodePengujian extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<K3PengusahaanMetodePengujianFactory> */
    use HasFactory;

    protected $table = 'k3_pengusahaan_metode_pengujians';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_urut',
        'nama_peralatan',
        'no_pengesahan',
        'nama_kategori_alat',
        'uji_visual',
        'uji_fungsi',
        'uji_beban',
        'uji_hydro',
        'ndt',
        'uji_ultrasonic_thickness',
        'uji_ketahanan',
        'sertifikasi_terakhir',
        'sertifikasi_ulang',
        'keterangan',
        'sort_order',
        'input_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
