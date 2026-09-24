<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3MetodePengujianPeralatan extends Model
{
    use HasFactory;

    protected $table = 'k3_metode_pengujian_peralatans';

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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
