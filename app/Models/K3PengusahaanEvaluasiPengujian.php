<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanEvaluasiPengujianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanEvaluasiPengujian extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<K3PengusahaanEvaluasiPengujianFactory> */
    use HasFactory;

    protected $table = 'k3_pengusahaan_evaluasi_pengujians';

    protected $fillable = [
        'unit_id',
        'year',
        'no_urut',
        'nama_kategori_alat',
        'jenis',
        'kapasitas',
        'temuan_sertifikat',
        'progres_bulan',
        'keterangan',
        'sort_order',
        'input_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progres_bulan' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
