<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class K3PengusahaanEvaluasiPengujianMeta extends Model
{
    use BelongsToUnit;
    use HasFactory;

    protected $table = 'k3_pengusahaan_evaluasi_pengujian_meta';

    protected $fillable = [
        'unit_id',
        'year',
        'nomor_dokumen',
        'tanggal_terbit',
        'revisi',
        'halaman',
        'catatan',
    ];
}
