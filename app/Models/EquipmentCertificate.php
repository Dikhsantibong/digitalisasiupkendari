<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\EquipmentCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An equipment certificate/test record per unit. Its monitoring status and
 * remaining days are derived from {@see $uji_ulang_tanggal}, not stored.
 *
 * @property int $id
 * @property int $unit_id
 * @property int|null $equipment_category_id
 * @property string $jenis
 * @property Carbon|null $ijin_awal_tanggal
 * @property Carbon|null $uji_terakhir_tanggal
 * @property Carbon|null $uji_ulang_tanggal
 */
#[Fillable([
    'unit_id', 'equipment_category_id', 'jenis', 'kapasitas', 'lokasi',
    'merk_manufacture', 'no_seri', 'regulasi', 'ijin_awal_nomor', 'ijin_awal_tanggal',
    'uji_terakhir_nomor', 'uji_terakhir_tanggal', 'uji_ulang_tanggal', 'batasan_uji',
    'masa_berlaku_tahun', 'keterangan', 'input_by',
])]
class EquipmentCertificate extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<EquipmentCertificateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ijin_awal_tanggal' => 'date',
            'uji_terakhir_tanggal' => 'date',
            'uji_ulang_tanggal' => 'date',
        ];
    }

    /**
     * @return BelongsTo<EquipmentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }
}
