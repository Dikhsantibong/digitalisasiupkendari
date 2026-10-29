<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laporan Kondisi Abnormal dan Gangguan Pembangkit (Modul OPERASI).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $no_urut
 * @property string|null $uraian_kondisi
 * @property Carbon|null $tanggal
 * @property int $is_abnormal
 * @property string $durasi_abnormal
 * @property int $is_gangguan
 * @property string $durasi_gangguan
 * @property string|null $parameter_terkait
 * @property bool $tl_pendek_sr tindak lanjut jangka pendek: Service Request
 * @property bool $tl_pendek_flm tindak lanjut jangka pendek: FLM
 * @property bool $tl_pendek_lainnya tindak lanjut jangka pendek: lainnya
 * @property string|null $tl_pendek_keterangan
 * @property string|null $tl_panjang tindak lanjut jangka panjang
 * @property string|null $dampak_sistem
 * @property string|null $dampak_pembangkit
 * @property string|null $jam_mulai gangguan only, "HH:MM"
 * @property string|null $jam_selesai gangguan only, "HH:MM"
 * @property int|null $machine_id the mesin that had the gangguan
 * @property string|null $peralatan the failed part, e.g. cylinder head
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read User|null $inputUser
 * @property-read Machine|null $machine
 */
#[Fillable([
    'unit_id',
    'year',
    'month',
    'no_urut',
    'uraian_kondisi',
    'tanggal',
    'is_abnormal',
    'durasi_abnormal',
    'is_gangguan',
    'durasi_gangguan',
    'jam_mulai',
    'jam_selesai',
    'machine_id',
    'peralatan',
    'parameter_terkait',
    'tl_pendek_sr',
    'tl_pendek_flm',
    'tl_pendek_lainnya',
    'tl_pendek_keterangan',
    'tl_panjang',
    'dampak_sistem',
    'dampak_pembangkit',
    'input_by',
])]
class KondisiAbnormal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'kondisi_abnormals';

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
            'tanggal' => 'date:Y-m-d',
            'is_abnormal' => 'integer',
            'durasi_abnormal' => 'decimal:2',
            'is_gangguan' => 'integer',
            'durasi_gangguan' => 'decimal:2',
            'machine_id' => 'integer',
            'tl_pendek_sr' => 'boolean',
            'tl_pendek_flm' => 'boolean',
            'tl_pendek_lainnya' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
