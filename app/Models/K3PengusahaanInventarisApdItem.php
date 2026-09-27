<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanInventarisApdItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_inventaris_apd_items';

    protected $fillable = [
        'laporan_id',
        'kategori',
        'no_grup',
        'nama_grup',
        'nama_alat',
        'jumlah',
        'lokasi',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'no_grup' => 'integer',
            'jumlah' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanInventarisApd::class, 'laporan_id');
    }
}
