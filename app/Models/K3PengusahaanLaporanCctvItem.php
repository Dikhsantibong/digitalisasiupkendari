<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanLaporanCctvItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_laporan_cctv_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'tanggal',
        'lokasi_cctv',
        'waktu_pantau',
        'kondisi_pantau',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'laporan_id' => 'integer',
            'no_urut' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanLaporanCctv::class, 'laporan_id');
    }
}
