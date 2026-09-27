<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanInventarisApd extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_inventaris_apds';

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
        // KATEGORI I: Peralatan Keselamatan Kerja Utama
        [
            'kategori' => 'I',
            'no_grup' => 1,
            'nama_grup' => 'Sarung Tangan :',
            'nama_alat' => 'Sarung tangan kain bintik',
            'jumlah' => 4,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 1,
            'nama_grup' => 'Sarung Tangan :',
            'nama_alat' => 'Sarung tangan karet',
            'jumlah' => 1,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 1,
            'nama_grup' => 'Sarung Tangan :',
            'nama_alat' => 'Sarung tangan kulit (las)',
            'jumlah' => 1,
            'lokasi' => 'K2LH',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 1,
            'nama_grup' => 'Sarung Tangan :',
            'nama_alat' => 'Sarung tangan anti panas',
            'jumlah' => 2,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 2,
            'nama_grup' => null,
            'nama_alat' => 'Pelindung Lengan',
            'jumlah' => 0,
            'lokasi' => null,
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 3,
            'nama_grup' => null,
            'nama_alat' => 'Helm',
            'jumlah' => 71,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => 'Pegawai dan mitra kerja',
        ],
        [
            'kategori' => 'I',
            'no_grup' => 4,
            'nama_grup' => null,
            'nama_alat' => 'Helm Tamu',
            'jumlah' => 25,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => 'K2LH, Pos Security, dan Lemari K3',
        ],
        [
            'kategori' => 'I',
            'no_grup' => 5,
            'nama_grup' => 'Sepatu :',
            'nama_alat' => 'Sepatu Tamu',
            'jumlah' => 7,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 5,
            'nama_grup' => 'Sepatu :',
            'nama_alat' => 'Safety shoes',
            'jumlah' => 71,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => 'Pegawai dan mitra kerja',
        ],
        [
            'kategori' => 'I',
            'no_grup' => 5,
            'nama_grup' => 'Sepatu :',
            'nama_alat' => 'Sepatu 20 kV',
            'jumlah' => 3,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 5,
            'nama_grup' => 'Sepatu :',
            'nama_alat' => 'Sepatu tahan panas',
            'jumlah' => 1,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 6,
            'nama_grup' => 'Pelindung Muka dan Mata',
            'nama_alat' => 'Kacamata bengkel',
            'jumlah' => 2,
            'lokasi' => 'Workshop',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 6,
            'nama_grup' => 'Pelindung Muka dan Mata',
            'nama_alat' => 'Kacamata las listrik',
            'jumlah' => 2,
            'lokasi' => 'Workshop',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 6,
            'nama_grup' => 'Pelindung Muka dan Mata',
            'nama_alat' => 'Pelindung muka bengkel',
            'jumlah' => 1,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 6,
            'nama_grup' => 'Pelindung Muka dan Mata',
            'nama_alat' => 'Pelindung muka las',
            'jumlah' => 1,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 7,
            'nama_grup' => 'Pakaian Kerja :',
            'nama_alat' => 'Pakaian kerja biasa',
            'jumlah' => 0,
            'lokasi' => null,
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 7,
            'nama_grup' => 'Pakaian Kerja :',
            'nama_alat' => 'Pakaian anti panas',
            'jumlah' => 1,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 7,
            'nama_grup' => 'Pakaian Kerja :',
            'nama_alat' => 'Jas hujan',
            'jumlah' => 3,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => 'K2LH, Pos Security, dan Operator',
        ],
        [
            'kategori' => 'I',
            'no_grup' => 8,
            'nama_grup' => null,
            'nama_alat' => 'Pelindung Dada Las',
            'jumlah' => 0,
            'lokasi' => null,
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 9,
            'nama_grup' => null,
            'nama_alat' => 'Body Harness',
            'jumlah' => 1,
            'lokasi' => 'Harlist',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 10,
            'nama_grup' => 'Pelindung Telinga',
            'nama_alat' => 'Ear muff/Ear protector',
            'jumlah' => 6,
            'lokasi' => 'K2LH & Security',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 10,
            'nama_grup' => 'Pelindung Telinga',
            'nama_alat' => 'Ear plug',
            'jumlah' => 71,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 11,
            'nama_grup' => 'Pelindung Pernafasan :',
            'nama_alat' => 'Masker kain',
            'jumlah' => 8,
            'lokasi' => 'K2LH',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 11,
            'nama_grup' => 'Pelindung Pernafasan :',
            'nama_alat' => 'Masker kimia',
            'jumlah' => 14,
            'lokasi' => 'K3',
            'keterangan' => null,
        ],
        [
            'kategori' => 'I',
            'no_grup' => 12,
            'nama_grup' => null,
            'nama_alat' => 'Baju Pelampung',
            'jumlah' => 1,
            'lokasi' => 'Lemari K3',
            'keterangan' => null,
        ],

        // KATEGORI II: Peralatan Keselamatan Kerja Pelengkap
        [
            'kategori' => 'II',
            'no_grup' => 1,
            'nama_grup' => 'Tangga',
            'nama_alat' => 'Tangga biasa',
            'jumlah' => 1,
            'lokasi' => 'Logistik',
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 1,
            'nama_grup' => 'Tangga',
            'nama_alat' => 'Tangga berkait',
            'jumlah' => 1,
            'lokasi' => 'Pemeliharaan',
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 1,
            'nama_grup' => 'Tangga',
            'nama_alat' => 'Tangga sambungan',
            'jumlah' => 0,
            'lokasi' => null,
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 1,
            'nama_grup' => 'Tangga',
            'nama_alat' => 'Tangga berdiri',
            'jumlah' => 2,
            'lokasi' => 'Logistik',
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 2,
            'nama_grup' => 'Lampu Penerangan :',
            'nama_alat' => 'Senter',
            'jumlah' => 10,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => 'K2LH, Pos Security, Lemari K3 & Operator',
        ],
        [
            'kategori' => 'II',
            'no_grup' => 2,
            'nama_grup' => 'Lampu Penerangan :',
            'nama_alat' => 'Lampu darurat/emergency',
            'jumlah' => 15,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => 'CCR, Kubikel 6,32 KV, R. Tools, Toilet dan Lokal Pembangkit',
        ],
        [
            'kategori' => 'II',
            'no_grup' => 2,
            'nama_grup' => 'Lampu Penerangan :',
            'nama_alat' => 'Lampu TR (penerangan tempat kerja)',
            'jumlah' => 1,
            'lokasi' => 'Pemeliharaan',
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 3,
            'nama_grup' => null,
            'nama_alat' => 'Sirkulator Udara/Kipas',
            'jumlah' => 1,
            'lokasi' => 'ULPLTD Poasia',
            'keterangan' => 'Logistik Dan kantor',
        ],
        [
            'kategori' => 'II',
            'no_grup' => 4,
            'nama_grup' => null,
            'nama_alat' => 'TOA',
            'jumlah' => 1,
            'lokasi' => 'K2LH',
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 5,
            'nama_grup' => null,
            'nama_alat' => 'Tongkat Rambu-Rambu',
            'jumlah' => 2,
            'lokasi' => 'Pos Security',
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 6,
            'nama_grup' => null,
            'nama_alat' => 'Rompi Nyala',
            'jumlah' => 4,
            'lokasi' => 'K2LH',
            'keterangan' => null,
        ],
        [
            'kategori' => 'II',
            'no_grup' => 7,
            'nama_grup' => null,
            'nama_alat' => 'Lampu Sorot',
            'jumlah' => 1,
            'lokasi' => 'K2LH',
            'keterangan' => null,
        ],
    ];

    /**
     * Build the default rows array with empty IDs for Inertia view.
     *
     * @return list<array{id: null, kategori: string, no_grup: int, nama_grup: ?string, nama_alat: string, jumlah: ?int, lokasi: ?string, keterangan: ?string, sort_order: int}>
     */
    public static function buildDefaultRows(): array
    {
        $rows = [];
        foreach (self::DEFAULT_ITEMS as $index => $item) {
            $rows[] = [
                'id' => null,
                'kategori' => $item['kategori'],
                'no_grup' => $item['no_grup'],
                'nama_grup' => $item['nama_grup'],
                'nama_alat' => $item['nama_alat'],
                'jumlah' => $item['jumlah'],
                'lokasi' => $item['lokasi'] ?? '',
                'keterangan' => $item['keterangan'] ?? '',
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
        return $this->hasMany(K3PengusahaanInventarisApdItem::class, 'laporan_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
