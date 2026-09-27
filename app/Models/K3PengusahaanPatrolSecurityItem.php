<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanPatrolSecurityItem extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_patrol_security_items';

    protected $fillable = [
        'laporan_id',
        'lokasi_kode',
        'lokasi_nama',
        'scans',
        'total',
        'persentase',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'laporan_id' => 'integer',
            'scans' => 'array',
            'total' => 'integer',
            'persentase' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(K3PengusahaanPatrolSecurity::class, 'laporan_id');
    }
}
