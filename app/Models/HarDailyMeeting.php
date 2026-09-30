<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\HarDailyMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Daily Meeting Pemeliharaan: one meeting (acara, hari/tanggal, waktu, tempat)
 * with its attendance list, filled by the attendees themselves through the QR
 * code (public link /hadir/{token}) with a canvas signature, plus eviden photos.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $token
 * @property int $year
 * @property int $month
 * @property Carbon $tanggal
 * @property string $acara
 * @property string|null $waktu
 * @property string|null $tempat
 * @property list<array{uid: string, nama: string, asal: string|null, jabatan: string|null, ttd: string|null, hadir_pada: string|null, via: string}> $peserta
 * @property list<string> $eviden
 * @property bool $absensi_dibuka
 */
#[Fillable(['unit_id', 'year', 'month', 'tanggal', 'acara', 'waktu', 'tempat', 'peserta', 'eviden', 'absensi_dibuka', 'input_by'])]
class HarDailyMeeting extends Model
{
    /** @use HasFactory<HarDailyMeetingFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'har_daily_meetings';

    protected static function booted(): void
    {
        static::creating(function (HarDailyMeeting $meeting): void {
            $meeting->token ??= Str::random(32);
        });
    }

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
            'absensi_dibuka' => 'boolean',
        ];
    }
}
