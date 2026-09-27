<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanInspeksiTempatKerjaItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_inspeksi_tempat_kerja_items';

    protected $fillable = [
        'laporan_id',
        'category',
        'no_urut',
        'item',
        'status',
        'comment',
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
        return $this->belongsTo(K3PengusahaanInspeksiTempatKerja::class, 'laporan_id');
    }
}
