<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanPemeriksaanP3kItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_pemeriksaan_p3k_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'nama_isi',
        'standar_jumlah',
        'satuan',
        'kondisi_lokasi',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'no_urut' => 'integer',
            'standar_jumlah' => 'integer',
            'kondisi_lokasi' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanPemeriksaanP3k::class, 'laporan_id');
    }
}
