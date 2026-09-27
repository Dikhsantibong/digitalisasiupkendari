<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanAlatTanggapDaruratItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_alat_tanggap_darurat_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'jenis',
        'siap_pakai',
        'kadaluarsa',
        'kosong',
        'tgl_diisi_kembali',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'no_urut' => 'integer',
            'siap_pakai' => 'integer',
            'kadaluarsa' => 'integer',
            'kosong' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanAlatTanggapDarurat::class, 'laporan_id');
    }
}
