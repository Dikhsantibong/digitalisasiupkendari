<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanFireAlarmItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_fire_alarm_items';

    protected $fillable = [
        'laporan_id',
        'no_urut',
        'lokasi',
        'tanggal_periksa',
        'kondisi',
        'panel_indikator',
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
        return $this->belongsTo(K3PengusahaanFireAlarm::class, 'laporan_id');
    }
}
