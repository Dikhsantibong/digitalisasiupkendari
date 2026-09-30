<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperatorMutasiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lembar Mutasi Operator: the shift handover sheet of a unit for one date +
 * shift. Filled and signed by the handing-over regu, then accepted (signed) by
 * the receiving regu, which locks it.
 *
 * @property int $id
 * @property int $unit_id
 * @property Carbon $tanggal
 * @property string $shift pagi|sore|malam
 * @property list<array{machine_id: int|null, nama: string, level_bbm: string|null, tambah_bbm: string|null, pelumas: string|null, status: string|null}> $mesin
 * @property list<array{nama: string, level_cm: string|null}> $tangki
 * @property list<array{nama: string, ada: bool, jumlah: int|null}> $peralatan
 * @property list<array{jam: string|null, uraian: string}> $kejadian
 * @property string|null $gangguan_mesin
 * @property string|null $catatan
 * @property string|null $regu_penyerah
 * @property string|null $penyerah_nama
 * @property string|null $paraf_penyerah
 * @property Carbon|null $diserahkan_at
 * @property string|null $regu_penerima
 * @property string|null $penerima_nama
 * @property string|null $paraf_penerima
 * @property Carbon|null $diterima_at
 */
#[Fillable([
    'unit_id', 'tanggal', 'shift', 'mesin', 'tangki', 'peralatan', 'kejadian', 'gangguan_mesin', 'catatan',
    'regu_penyerah', 'penyerah_nama', 'paraf_penyerah', 'diserahkan_at',
    'regu_penerima', 'penerima_nama', 'paraf_penerima', 'diterima_at', 'input_by',
])]
class OperatorMutasi extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<OperatorMutasiFactory> */
    use HasFactory;

    /**
     * Shifts of the sheet with their hours (WITA), in order.
     *
     * @var array<string, string>
     */
    public const SHIFTS = [
        'pagi' => '08:00 s/d 16:00 WITA',
        'sore' => '16:00 s/d 22:00 WITA',
        'malam' => '22:00 s/d 08:00 WITA',
    ];

    /** @var list<string> */
    public const PELUMAS = ['normal', 'rendah', 'tinggi'];

    /** @var list<string> */
    public const STATUS_MESIN = ['operasi', 'standby', 'gangguan'];

    public function isReceived(): bool
    {
        return $this->diterima_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'mesin' => 'array',
            'tangki' => 'array',
            'peralatan' => 'array',
            'kejadian' => 'array',
            'diserahkan_at' => 'datetime',
            'diterima_at' => 'datetime',
        ];
    }
}
