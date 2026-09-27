<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class K3PengusahaanLaporanCctv extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_laporan_cctvs';

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
     * Standard 8 CCTV locations from official document SMT-FM-AK3-14.01.
     *
     * @var list<string>
     */
    public const DEFAULT_LOCATIONS = [
        '1. Ruang Pembangkit (Lokal)',
        '2. Depan Kantor Unit',
        '3. Depan Pos Satpam',
        '4. PLNT (eks)',
        '5. Tangki Timbun HSD',
        '6. Oil Trap',
        '7. Pos BBM & Gudang',
        '8. Area Pembongkaran 2 BBM',
    ];

    /**
     * Build default items array for the given year and month.
     *
     * @return list<array{id: null, no_urut: int, tanggal: string, lokasi_cctv: string, waktu_pantau: string, kondisi_pantau: string, keterangan: string, sort_order: int}>
     */
    public static function buildDefaultRows(int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $dateStr = sprintf('%d/%d/%d', $daysInMonth, $month, $year);

        return [
            [
                'id' => null,
                'no_urut' => 1,
                'tanggal' => $dateStr,
                'lokasi_cctv' => implode("\n", self::DEFAULT_LOCATIONS),
                'waktu_pantau' => 'Setiap Saat',
                'kondisi_pantau' => 'Aman',
                'keterangan' => '',
                'sort_order' => 0,
            ],
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanLaporanCctvItem::class, 'laporan_id')->orderBy('sort_order')->orderBy('no_urut');
    }
}
