<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanApatItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_apat_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'nama_alat',
        'tanggal_inspeksi',
        'kondisi',
        'jumlah',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'no_urut' => 'integer',
            'jumlah' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanApat::class, 'laporan_id');
    }
}
