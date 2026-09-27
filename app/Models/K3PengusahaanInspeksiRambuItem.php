<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanInspeksiRambuItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_inspeksi_rambu_items';

    protected $fillable = [
        'laporan_id',
        'no_id',
        'rambu_k3',
        'lokasi',
        'tingkat_pelanggaran',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'laporan_id' => 'integer',
            'no_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanInspeksiRambu::class, 'laporan_id');
    }
}
