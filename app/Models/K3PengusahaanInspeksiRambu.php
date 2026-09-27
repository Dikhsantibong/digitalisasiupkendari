<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanInspeksiRambu extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_inspeksi_rambus';

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

    /**
     * Standard 34 K3 safety signs from official document SMT-FM-AK3-07.01.
     *
     * @var list<array{no_id: int, rambu_k3: string, lokasi: string, tingkat_pelanggaran: string, keterangan: string}>
     */
    public const DEFAULT_ITEMS = [
        ['no_id' => 1, 'rambu_k3' => 'Gunakan Helm', 'lokasi' => 'Ruang Pembangkit', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 2, 'rambu_k3' => 'Gunakan Pelindung Telinga', 'lokasi' => 'Ruang Pembangkit', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 3, 'rambu_k3' => 'Gunakan Sepatu Safety', 'lokasi' => 'Ruang Pembangkit', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 4, 'rambu_k3' => 'Gunakan Helm', 'lokasi' => 'Workshop', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 5, 'rambu_k3' => 'Gunakan Pelindung Telinga', 'lokasi' => 'Workshop', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 6, 'rambu_k3' => 'Dilarang Merokok', 'lokasi' => 'Pencucian Filter', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 7, 'rambu_k3' => 'Gunakan Kacamata Safety', 'lokasi' => 'Workshop', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 8, 'rambu_k3' => 'Gunakan Masker', 'lokasi' => 'Workshop', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 9, 'rambu_k3' => 'Gunakan Sarung Tangan', 'lokasi' => 'Workshop', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 10, 'rambu_k3' => 'Gunakan Sepatu Safety', 'lokasi' => 'Workshop', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 11, 'rambu_k3' => 'Bahaya Tegangan Tinggi', 'lokasi' => 'Trafo', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Waspada'],
        ['no_id' => 12, 'rambu_k3' => 'Bahaya Kematian', 'lokasi' => 'Trafo', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Waspada'],
        ['no_id' => 13, 'rambu_k3' => 'Dilarang Menggali Tanpa Izin', 'lokasi' => 'Trafo', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Awas'],
        ['no_id' => 14, 'rambu_k3' => 'Gunakan Helm', 'lokasi' => 'Trafo', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 15, 'rambu_k3' => 'Gunakan Sepatu 20 Kv', 'lokasi' => 'Trafo', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 16, 'rambu_k3' => 'Hati-hati Saat Naik Tangga', 'lokasi' => 'Storage BBM', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Waspada'],
        ['no_id' => 17, 'rambu_k3' => 'Bahaya Terjatuh', 'lokasi' => 'Storage BBM', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Waspada'],
        ['no_id' => 18, 'rambu_k3' => 'Cairan Mudah Terbakar', 'lokasi' => 'Storage BBM', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 19, 'rambu_k3' => 'Dilarang Merokok', 'lokasi' => 'Storage BBM', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 20, 'rambu_k3' => 'Dilarang Menyalakan Pemantik api', 'lokasi' => 'Storage BBM', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 21, 'rambu_k3' => 'Bahaya Terpeleset', 'lokasi' => 'Oil Catcher 2', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 22, 'rambu_k3' => 'Dilarang Merokok', 'lokasi' => 'TPS LB3', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 23, 'rambu_k3' => 'Bahaya Terjatuh', 'lokasi' => 'Oil Catcher 2', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 24, 'rambu_k3' => 'Cairan Mudah Terbakar', 'lokasi' => 'Oil Catcher 2', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 25, 'rambu_k3' => 'Dilarang Merokok', 'lokasi' => 'Oil Catcher 2', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 26, 'rambu_k3' => 'Dilarang Menyalakan Pemantik Api', 'lokasi' => 'Oil Catcher 2', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 27, 'rambu_k3' => 'Bahaya Tegangan Tinggi', 'lokasi' => 'Switch Gear', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Waspada'],
        ['no_id' => 28, 'rambu_k3' => 'Gunakan Pelindung Hidung', 'lokasi' => 'Area Cerobong', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Perhatian'],
        ['no_id' => 29, 'rambu_k3' => 'Dilarang Merokok', 'lokasi' => 'PLN-T', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 30, 'rambu_k3' => 'Titik Kumpul', 'lokasi' => 'Lapangan', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Penting'],
        ['no_id' => 31, 'rambu_k3' => 'Awas Tertimpa Material', 'lokasi' => 'Tangga Gedung Pembangkit', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Waspada'],
        ['no_id' => 32, 'rambu_k3' => 'Dilarang Merokok', 'lokasi' => 'POS BBM', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Bahaya'],
        ['no_id' => 33, 'rambu_k3' => 'Jalur Evakuasi', 'lokasi' => 'Setiap Area', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Penting'],
        ['no_id' => 34, 'rambu_k3' => 'Segitiga APAR', 'lokasi' => 'Setiap APAR', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Penting'],
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanInspeksiRambuItem::class, 'laporan_id')->orderBy('sort_order')->orderBy('no_id');
    }
}
