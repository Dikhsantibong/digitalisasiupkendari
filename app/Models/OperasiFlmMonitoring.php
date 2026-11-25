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
 * @property string|null $foto_awal eviden photo of the kondisi awal (public disk path)
 * @property string|null $foto_akhir eviden photo of the kondisi akhir (public disk path)
 * @property string|null $catatan
 * @property string $status open|close
 * @property int|null $input_by
 * @property string|null $uid stable key of a finding across the page's save-all
 * @property int|null $reported_by the account that reported it
 * @property string|null $reporter_name
 * @property string|null $reporter_shift e.g. "Sore · Regu B"
 * @property Carbon|null $reported_at
 * @property int|null $closed_by
 * @property string|null $closed_by_name
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'mesin', 'tanggal', 'masalah', 'kondisi_awal', 'kondisi_akhir', 'foto_awal', 'foto_akhir', 'catatan', 'status', 'input_by', 'uid', 'reported_by', 'reporter_name', 'reporter_shift', 'reported_at', 'closed_by', 'closed_by_name', 'closed_at'])]
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
            'reported_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** Days a finding still open has waited since it was found (or reported). */
    public function openDays(?Carbon $now = null): ?int
    {
        if ($this->status !== 'open') {
            return null;
        }
        $since = $this->tanggal ?? $this->reported_at ?? $this->created_at;

        if ($since === null) {
            return null;
        }

        // Calendar days in WITA (the date column has no time zone).
        $today = ($now ?? Carbon::now('Asia/Makassar'))->copy()->timezone('Asia/Makassar')->toDateString();
        $from = $this->tanggal !== null ? $this->tanggal->toDateString() : $since->copy()->timezone('Asia/Makassar')->toDateString();

        return max(0, (int) Carbon::parse($from)->diffInDays(Carbon::parse($today)));
    }

    /**
     * The shift of a moment (WITA) with the employee's regu, e.g. "Sore · Regu B":
     * pagi 08–16, sore 16–22, malam 22–08 (as the Lembar Mutasi).
     */
    public static function shiftLabel(?Employee $employee, ?Carbon $at = null): string
    {
        $hour = ($at ?? Carbon::now('Asia/Makassar'))->copy()->timezone('Asia/Makassar')->hour;
        $shift = $hour >= 8 && $hour < 16 ? 'Pagi' : ($hour >= 16 && $hour < 22 ? 'Sore' : 'Malam');

        return $employee?->regu ? "{$shift} · Regu {$employee->regu}" : $shift;
    }
}
