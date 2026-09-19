<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PdmKesiapanApdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One inspected item of the PdM "Kesiapan APD Bagian PdM Pembangkit" form.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $kelompok
 * @property int|null $no_urut
 * @property string $inspeksi
 * @property int|null $jumlah
 * @property string|null $satuan
 * @property string|null $kelayakan_apd
 * @property string|null $peralatan_jumlah
 * @property string|null $peralatan_kelayakan
 * @property string|null $sop_pnp
 * @property string|null $sop_vendor
 * @property string|null $p3k_kotak
 * @property string|null $p3k_isi
 * @property string|null $cara_kerja
 * @property string|null $keterangan
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable([
    'unit_id', 'year', 'month', 'kelompok', 'no_urut', 'inspeksi', 'jumlah', 'satuan', 'kelayakan_apd',
    'peralatan_jumlah', 'peralatan_kelayakan', 'sop_pnp', 'sop_vendor', 'p3k_kotak', 'p3k_isi', 'cara_kerja',
    'keterangan', 'sort_order', 'input_by',
])]
class PdmKesiapanApd extends Model
{
    /** @use HasFactory<PdmKesiapanApdFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'pdm_kesiapan_apds';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'no_urut' => 'integer',
            'jumlah' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
