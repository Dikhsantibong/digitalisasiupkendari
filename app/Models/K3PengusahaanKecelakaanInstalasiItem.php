<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanKecelakaanInstalasiItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_kecelakaan_instalasi_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'tanggal_kejadian',
        'fungsi',
        'lokasi_kejadian',
        'luka_ringan',
        'luka_berat',
        'meninggal',
        'kerugian_material',
        'keterangan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'laporan_id' => 'integer',
            'no_urut' => 'integer',
            'luka_ringan' => 'integer',
            'luka_berat' => 'integer',
            'meninggal' => 'integer',
            'kerugian_material' => 'float',
        ];
    }

    /**
     * @return BelongsTo<K3PengusahaanKecelakaanInstalasi, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanKecelakaanInstalasi::class, 'laporan_id');
    }
}
