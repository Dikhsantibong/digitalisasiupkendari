<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanApelKeamananItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_apel_keamanan_items';

    protected $fillable = [
        'laporan_id',
        'tanggal',
        'hari_ke',
        'tim_regu',
        'shift',
        'waktu_apel',
        'jumlah_personil',
        'kelengkapan_atribut',
        'paraf_komandan_regu',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'hari_ke' => 'integer',
            'jumlah_personil' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanApelKeamanan::class, 'laporan_id');
    }
}
