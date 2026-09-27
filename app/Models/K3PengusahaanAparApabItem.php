<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanAparApabItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_apar_apab_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'no_rfid',
        'lokasi',
        'tgl_periksa',
        'merk_apar',
        'jenis_apar',
        'berat_kg',
        'kondisi_tabung',
        'kondisi_nozzle_selang',
        'indikator_tekanan',
        'kondisi_pin_segel',
        'keterangan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'no_urut' => 'integer',
            'berat_kg' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanAparApab::class, 'laporan_id');
    }
}
