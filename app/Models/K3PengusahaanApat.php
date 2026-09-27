<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanApat extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_apats';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'tanggal_inspeksi',
        'catatan',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'input_by' => 'integer',
        ];
    }

    public const DEFAULT_ITEMS = [
        ['nama_alat' => 'TONG PASIR', 'jumlah' => 3, 'kondisi' => 'Baik', 'keterangan' => ''],
        ['nama_alat' => 'TONG AIR', 'jumlah' => 2, 'kondisi' => 'Baik', 'keterangan' => ''],
        ['nama_alat' => 'SKOP', 'jumlah' => 5, 'kondisi' => 'Baik', 'keterangan' => ''],
        ['nama_alat' => 'KARUNG GONI', 'jumlah' => 4, 'kondisi' => 'Baik', 'keterangan' => ''],
        ['nama_alat' => 'EMBER', 'jumlah' => 2, 'kondisi' => 'Baik', 'keterangan' => ''],
        ['nama_alat' => 'GAYUNG', 'jumlah' => 2, 'kondisi' => 'Baik', 'keterangan' => ''],
        ['nama_alat' => 'KENTONGAN', 'jumlah' => 1, 'kondisi' => 'Baik', 'keterangan' => ''],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildDefaultRows(?string $defaultDate = null): array
    {
        return array_map(function (array $item, int $index) use ($defaultDate): array {
            return [
                'no_urut' => $index + 1,
                'nama_alat' => $item['nama_alat'],
                'tanggal_inspeksi' => $defaultDate ?? '',
                'kondisi' => $item['kondisi'],
                'jumlah' => $item['jumlah'],
                'keterangan' => $item['keterangan'] ?? '',
                'sort_order' => $index,
            ];
        }, self::DEFAULT_ITEMS, array_keys(self::DEFAULT_ITEMS));
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanApatItem::class, 'laporan_id')->orderBy('sort_order');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
