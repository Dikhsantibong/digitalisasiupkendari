<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanBukuTamuItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_buku_tamu_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'tanggal',
        'jumlah_kehadiran_tamu',
        'tamu_pln',
        'instansi',
        'kontraktor',
        'lainnya',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'laporan_id' => 'integer',
            'no_urut' => 'integer',
            'jumlah_kehadiran_tamu' => 'integer',
            'tamu_pln' => 'integer',
            'instansi' => 'integer',
            'kontraktor' => 'integer',
            'lainnya' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanBukuTamu::class, 'laporan_id');
    }
}
