<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3KondisiK3Meta extends Model
{
    use HasFactory;

    protected $table = 'k3_kondisi_k3_meta';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'catatan',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
