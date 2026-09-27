<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanAlatTanggapDarurat extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_alat_tanggap_darurats';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
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
        [
            'no_urut' => 1,
            'jenis' => 'Alat Pemadam Api Ringan (APAR)',
            'siap_pakai' => 26,
            'kadaluarsa' => 10,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 2,
            'jenis' => 'Alat Pemadam Api Berat (APAB)',
            'siap_pakai' => 4,
            'kadaluarsa' => 0,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 3,
            'jenis' => 'Alat Pemadam Api Tradisional (APAT)',
            'siap_pakai' => 3,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 4,
            'jenis' => 'Pompa Hydrant (Electrik)',
            'siap_pakai' => 1,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 5,
            'jenis' => 'Pompa Hydrant (Diesel)',
            'siap_pakai' => 1,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 6,
            'jenis' => 'Pompa Hydrant (Jocky)',
            'siap_pakai' => 1,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 7,
            'jenis' => 'Outdoor Hydrant + Busa (Tabung Stainless)',
            'siap_pakai' => 2,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 8,
            'jenis' => 'Indoor Hydrant + Busa (Tabung Stainless)',
            'siap_pakai' => 2,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 9,
            'jenis' => 'Out Door Hydrant',
            'siap_pakai' => 2,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
        [
            'no_urut' => 10,
            'jenis' => 'Kotak P3K',
            'siap_pakai' => 4,
            'kadaluarsa' => null,
            'kosong' => null,
            'tgl_diisi_kembali' => null,
            'keterangan' => 'Baik',
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildDefaultRows(): array
    {
        return array_map(function (array $item, int $index): array {
            return [
                'no_urut' => $index + 1,
                'jenis' => $item['jenis'],
                'siap_pakai' => $item['siap_pakai'],
                'kadaluarsa' => $item['kadaluarsa'],
                'kosong' => $item['kosong'],
                'tgl_diisi_kembali' => $item['tgl_diisi_kembali'] ?? '',
                'keterangan' => $item['keterangan'] ?? 'Baik',
                'sort_order' => $index,
            ];
        }, self::DEFAULT_ITEMS, array_keys(self::DEFAULT_ITEMS));
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanAlatTanggapDaruratItem::class, 'laporan_id')->orderBy('sort_order');
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
