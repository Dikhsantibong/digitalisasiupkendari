<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal Meeting Shift (OPERASI) — baris mark (realisasi harian) & baris shift
 * (huruf jadwal pagi/sore).
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'label', 'row_type', 'days', 'target', 'sort_order', 'input_by'])]
class OperasiMeetingShiftJadwal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_meeting_shift_jadwals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit_id' => 'integer', 'year' => 'integer', 'month' => 'integer', 'no_urut' => 'integer', 'days' => 'array', 'target' => 'integer', 'sort_order' => 'integer'];
    }
}
