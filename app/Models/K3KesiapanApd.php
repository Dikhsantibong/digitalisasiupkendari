<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3KesiapanApd extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'kelompok',
        'no_urut',
        'inspeksi',
        'apd_jumlah',
        'kelayakan_apd',
        'peralatan_jumlah',
        'peralatan_kelayakan',
        'sop_pnp',
        'sop_vendor',
        'p3k_ada',
        'p3k_memenuhi',
        'cara_kerja',
        'keterangan',
        'sort_order',
        'input_by',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
