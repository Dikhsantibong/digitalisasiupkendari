<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laporan Gangguan / Kerusakan Unit Pembangkit (Modul HAR, form LH-05).
 * Satu baris = satu laporan kejadian gangguan/kerusakan.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property string|null $nomor
 * @property Carbon|null $tanggal_laporan
 * @property string|null $hal
 * @property string $form_code
 * @property string|null $unit_kesatuan
 * @property int|null $machine_id
 * @property string|null $merek
 * @property string|null $type
 * @property string|null $no_seri
 * @property string|null $rh
 * @property string|null $jsb
 * @property string|null $jsmo
 * @property string|null $jsi_terakhir
 * @property string|null $fungsi_pembangkit
 * @property string|null $daya_terpasang
 * @property string|null $daya_mampu
 * @property string|null $tanggal_jam_kerusakan
 * @property string|null $peralatan_rusak
 * @property string|null $gejala
 * @property string|null $urutan_kejadian
 * @property string|null $parameter_terkait
 * @property string|null $analisa_penyebab
 * @property string|null $akibat
 * @property string|null $tindak_lanjut_pendek
 * @property string|null $tindak_lanjut_panjang
 * @property string|null $eviden
 * @property int $sort_order
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Machine|null $machine
 * @property-read User|null $inputUser
 */
#[Fillable([
    'unit_id',
    'year',
    'nomor',
    'tanggal_laporan',
    'hal',
    'form_code',
    'unit_kesatuan',
    'machine_id',
    'merek',
    'type',
    'no_seri',
    'rh',
    'jsb',
    'jsmo',
    'jsi_terakhir',
    'fungsi_pembangkit',
    'daya_terpasang',
    'daya_mampu',
    'tanggal_jam_kerusakan',
    'peralatan_rusak',
    'gejala',
    'urutan_kejadian',
    'parameter_terkait',
    'analisa_penyebab',
    'akibat',
    'tindak_lanjut_pendek',
    'tindak_lanjut_panjang',
    'eviden',
    'sort_order',
    'input_by',
])]
class HarLaporanGangguan extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'har_laporan_gangguans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'machine_id' => 'integer',
            'tanggal_laporan' => 'date:Y-m-d',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
