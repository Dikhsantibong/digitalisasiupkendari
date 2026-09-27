<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanPemeriksaanP3k extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_pemeriksaan_p3ks';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'locations',
        'catatan',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'locations' => 'array',
            'input_by' => 'integer',
        ];
    }

    public const DEFAULT_LOCATIONS = [
        'Ruang CCR',
        'Ruang Lobby',
        'Pos Security',
        'Area Lokal',
        'Kantor Unit',
        'Work shop',
        'TPS LB3',
    ];

    public const DEFAULT_ITEMS = [
        ['no_urut' => 1, 'nama_isi' => 'Kasa steril', 'standar_jumlah' => 20, 'satuan' => 'bh', 'keterangan' => 'Lengkap'],
        ['no_urut' => 2, 'nama_isi' => 'Perban Lebar 5 cm', 'standar_jumlah' => 2, 'satuan' => 'rol', 'keterangan' => 'Lengkap'],
        ['no_urut' => 3, 'nama_isi' => 'Perban Lebar 10 cm', 'standar_jumlah' => 2, 'satuan' => 'rol', 'keterangan' => 'Lengkap'],
        ['no_urut' => 4, 'nama_isi' => 'Plester Lebar 1,25 cm', 'standar_jumlah' => 2, 'satuan' => 'rol', 'keterangan' => 'Lengkap'],
        ['no_urut' => 5, 'nama_isi' => 'Plester Cepat', 'standar_jumlah' => 10, 'satuan' => 'lembar', 'keterangan' => 'Lengkap'],
        ['no_urut' => 6, 'nama_isi' => 'Kapas 25 gr', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 7, 'nama_isi' => 'Kain Mitela', 'standar_jumlah' => 2, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 8, 'nama_isi' => 'Gunting', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 9, 'nama_isi' => 'Peniti', 'standar_jumlah' => 12, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 10, 'nama_isi' => 'Sarung tangan', 'standar_jumlah' => 2, 'satuan' => 'pasang', 'keterangan' => 'Lengkap'],
        ['no_urut' => 11, 'nama_isi' => 'Masker', 'standar_jumlah' => 1, 'satuan' => 'kotak', 'keterangan' => 'Lengkap'],
        ['no_urut' => 12, 'nama_isi' => 'Pinset', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 13, 'nama_isi' => 'Lampu Senter', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 14, 'nama_isi' => 'Gelas Pencuci Mata', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 15, 'nama_isi' => 'Kantong Plastik', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 16, 'nama_isi' => 'Aquades', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 17, 'nama_isi' => 'Iodin Povidon', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 18, 'nama_isi' => 'Alkohol 70%', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 19, 'nama_isi' => 'Buku Panduan P3K', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
        ['no_urut' => 20, 'nama_isi' => 'Buku Daftar P3K', 'standar_jumlah' => 1, 'satuan' => 'buah', 'keterangan' => 'Lengkap'],
    ];

    /**
     * Build the default rows array with pre-populated values for Inertia view.
     *
     * @param  list<string>|null  $locations
     * @return list<array{id: null, no_urut: int, nama_isi: string, standar_jumlah: int, satuan: string, kondisi_lokasi: array<string, int>, keterangan: string, sort_order: int}>
     */
    public static function buildDefaultRows(?array $locations = null): array
    {
        $locations = $locations ?: self::DEFAULT_LOCATIONS;
        $rows = [];

        foreach (self::DEFAULT_ITEMS as $index => $item) {
            $kondisi = [];
            foreach ($locations as $loc) {
                // In scan, Lobby has 0 Kasa steril, others have standard
                if ($item['nama_isi'] === 'Kasa steril' && $loc === 'Ruang Lobby') {
                    $kondisi[$loc] = 0;
                } else {
                    $kondisi[$loc] = $item['standar_jumlah'];
                }
            }

            $rows[] = [
                'id' => null,
                'no_urut' => $item['no_urut'],
                'nama_isi' => $item['nama_isi'],
                'standar_jumlah' => $item['standar_jumlah'],
                'satuan' => $item['satuan'],
                'kondisi_lokasi' => $kondisi,
                'keterangan' => $item['keterangan'],
                'sort_order' => $index,
            ];
        }

        return $rows;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanPemeriksaanP3kItem::class, 'laporan_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
