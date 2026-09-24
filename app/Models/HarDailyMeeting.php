<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\HarDailyMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Formulir Daily Meeting Pemeliharaan: daftar hadir satu meeting beserta foto eviden.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property Carbon $tanggal
 * @property string $acara
 * @property string|null $waktu
 * @property string|null $tempat
 * @property list<array{nama: string, asal: string|null, jabatan: string|null}> $peserta
 * @property list<string> $eviden
 */
#[Fillable(['unit_id', 'year', 'month', 'tanggal', 'acara', 'waktu', 'tempat', 'peserta', 'eviden', 'input_by'])]
class HarDailyMeeting extends Model
{
    /** @use HasFactory<HarDailyMeetingFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'har_daily_meetings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'tanggal' => 'date',
            'peserta' => 'array',
            'eviden' => 'array',
        ];
    }
}
