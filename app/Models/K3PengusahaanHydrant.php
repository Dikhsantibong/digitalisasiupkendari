<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanHydrant extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_hydrants';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'tanggal_periksa',
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
        ['lokasi' => 'Depan Pos Security', 'hose' => 'Normal', 'nozzle' => 'Normal', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => 'Baik', 'keterangan' => ''],
        ['lokasi' => 'Depan Kantor Unit', 'hose' => 'Normal', 'nozzle' => 'Normal', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => 'Baik', 'keterangan' => ''],
        ['lokasi' => 'Depan Trafo', 'hose' => 'Normal', 'nozzle' => 'Normal', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => 'Baik', 'keterangan' => ''],
        ['lokasi' => 'Belakang Gedung Pembangkit (Outdoor)', 'hose' => 'Normal', 'nozzle' => 'Normal', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => 'Baik', 'keterangan' => ''],
        ['lokasi' => 'Pos BBM', 'hose' => 'Normal', 'nozzle' => 'Normal', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => 'Baik', 'keterangan' => ''],
        ['lokasi' => 'Depan Gedung Pembangkit (Indoor)', 'hose' => 'Normal', 'nozzle' => 'Normal', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => 'Baik', 'keterangan' => ''],
        ['lokasi' => 'Belakang Gedung Pembangkit (Indoor)', 'hose' => 'Normal', 'nozzle' => 'Normal', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => 'Baik', 'keterangan' => ''],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildDefaultRows(?string $defaultDate = null): array
    {
        return array_map(function (array $item, int $index) use ($defaultDate): array {
            return [
                'no_urut' => $index + 1,
                'lokasi' => $item['lokasi'],
                'tanggal_periksa' => $defaultDate ?? '',
                'hose' => $item['hose'],
                'nozzle' => $item['nozzle'],
                'box_hydrant' => $item['box_hydrant'],
                'kondisi_tekanan_air' => $item['kondisi_tekanan_air'],
                'keterangan' => $item['keterangan'] ?? '',
                'sort_order' => $index,
            ];
        }, self::DEFAULT_ITEMS, array_keys(self::DEFAULT_ITEMS));
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanHydrantItem::class, 'laporan_id')->orderBy('sort_order');
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
