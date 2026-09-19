<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperasiFlmMonitoringFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One finding of the Operasi input "Monitoring FLM".
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $no_urut
 * @property string|null $mesin
 * @property Carbon|null $tanggal
 * @property string|null $masalah
 * @property list<string>|null $kondisi_awal keys of OperasiFlmMonitoring::KONDISI_AWAL
 * @property string|null $kondisi_akhir
 * @property string|null $catatan
 * @property string $status open|close
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'mesin', 'tanggal', 'masalah', 'kondisi_awal', 'kondisi_akhir', 'catatan', 'status', 'input_by'])]
class OperasiFlmMonitoring extends Model
{
    /** @use HasFactory<OperasiFlmMonitoringFactory> */
    use BelongsToUnit, HasFactory;

    /** Kondisi awal actions => column label. */
    public const KONDISI_AWAL = [
        'bersihkan' => 'Bersihkan',
        'lumasi' => 'Lumasi',
        'kencangkan' => 'Kencangkan',
        'perbaikan_koneksi' => 'Perbaikan Koneksi',
        'lainnya' => 'Lainnya',
    ];

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
            'kondisi_awal' => 'array',
        ];
    }
}
