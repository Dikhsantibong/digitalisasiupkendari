<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3KondisiK3 extends Model
{
    use HasFactory;

    protected $table = 'k3_kondisi_k3s';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_urut',
        'periode',
        'kategori',
        'temuan',
        'kondisi',
        'tindak_lanjut',
        'rekomendasi',
        'lokasi',
        'keterangan',
        'eviden_sebelum',
        'eviden_sesudah',
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
