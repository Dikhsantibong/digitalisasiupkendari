<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanHydrantItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_hydrant_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'lokasi',
        'tanggal_periksa',
        'hose',
        'nozzle',
        'box_hydrant',
        'kondisi_tekanan_air',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'no_urut' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanHydrant::class, 'laporan_id');
    }
}
