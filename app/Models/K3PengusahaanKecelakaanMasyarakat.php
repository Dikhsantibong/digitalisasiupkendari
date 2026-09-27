<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanKecelakaanMasyarakatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanKecelakaanMasyarakat extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<K3PengusahaanKecelakaanMasyarakatFactory> */
    use HasFactory;

    protected $table = 'k3_pengusahaan_kecelakaan_masyarakats';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'is_nihil',
        'lampiran_teks',
        'nomor_keputusan',
        'catatan',
        'input_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'is_nihil' => 'boolean',
        ];
    }

    /**
     * @return HasMany<K3PengusahaanKecelakaanMasyarakatItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanKecelakaanMasyarakatItem::class, 'laporan_id')->orderBy('no_urut');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
