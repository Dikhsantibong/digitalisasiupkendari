<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class K3PengusahaanApelKeamanan extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_apel_keamanans';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'catatan',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'input_by' => 'integer',
        ];
    }

    /**
     * Standard 3 shifts per day.
     *
     * @var list<array{tim_regu: string, shift: string, waktu_apel: string}>
     */
    public const SHIFTS = [
        ['tim_regu' => 'A', 'shift' => 'Pagi', 'waktu_apel' => '08:00'],
        ['tim_regu' => 'B', 'shift' => 'Sore', 'waktu_apel' => '16:00'],
        ['tim_regu' => 'C', 'shift' => 'Malam', 'waktu_apel' => '22:00'],
    ];

    /**
     * Build the default rows array for every day of the given month and year.
     *
     * @return list<array{id: null, tanggal: string, hari_ke: int, tim_regu: string, shift: string, waktu_apel: string, jumlah_personil: int, kelengkapan_atribut: string, paraf_komandan_regu: ?string, keterangan: ?string, sort_order: int}>
     */
    public static function buildDefaultRows(int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $rows = [];
        $sortOrder = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

            foreach (self::SHIFTS as $shift) {
                $rows[] = [
                    'id' => null,
                    'tanggal' => $dateStr,
                    'hari_ke' => $day,
                    'tim_regu' => $shift['tim_regu'],
                    'shift' => $shift['shift'],
                    'waktu_apel' => $shift['waktu_apel'],
                    'jumlah_personil' => 2,
                    'kelengkapan_atribut' => 'Lengkap',
                    'paraf_komandan_regu' => null,
                    'keterangan' => null,
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return $rows;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanApelKeamananItem::class, 'laporan_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
