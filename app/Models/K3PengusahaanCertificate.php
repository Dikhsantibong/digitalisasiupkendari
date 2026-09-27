<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daftar Monitoring Sertifikasi Peralatan (Akses 2 — Pengusahaan K3) per unit.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $equipment_category_id
 * @property string $jenis
 * @property string|null $kapasitas
 * @property string|null $lokasi
 * @property string|null $merk_manufacture
 * @property string|null $no_seri
 * @property string|null $regulasi
 * @property string|null $ijin_awal_nomor
 * @property Carbon|null $ijin_awal_tanggal
 * @property string|null $uji_terakhir_nomor
 * @property Carbon|null $uji_terakhir_tanggal
 * @property Carbon|null $uji_ulang_tanggal
 * @property string|null $batasan_uji
 * @property int|null $masa_berlaku_tahun
 * @property string|null $keterangan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read EquipmentCategory|null $category
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'equipment_category_id',
    'jenis',
    'kapasitas',
    'lokasi',
    'merk_manufacture',
    'no_seri',
    'regulasi',
    'ijin_awal_nomor',
    'ijin_awal_tanggal',
    'uji_terakhir_nomor',
    'uji_terakhir_tanggal',
    'uji_ulang_tanggal',
    'batasan_uji',
    'masa_berlaku_tahun',
    'keterangan',
    'input_by',
])]
class K3PengusahaanCertificate extends Model
{
    /** @use HasFactory<K3PengusahaanCertificateFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_pengusahaan_certificates';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'equipment_category_id' => 'integer',
            'ijin_awal_tanggal' => 'date',
            'uji_terakhir_tanggal' => 'date',
            'uji_ulang_tanggal' => 'date',
            'masa_berlaku_tahun' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EquipmentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
