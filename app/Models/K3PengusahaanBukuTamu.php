<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanBukuTamu extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_buku_tamus';

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
     * Build default initial empty rows for the given year and month.
     *
     * @return list<array{id: null, no_urut: int, tanggal: string, jumlah_kehadiran_tamu: int, tamu_pln: int, instansi: int, kontraktor: int, lainnya: int, keterangan: string, sort_order: int}>
     */
    public static function buildDefaultRows(int $year, int $month): array
    {
        return [
            [
                'id' => null,
                'no_urut' => 1,
                'tanggal' => sprintf('1/%d/%d', $month, $year),
                'jumlah_kehadiran_tamu' => 0,
                'tamu_pln' => 0,
                'instansi' => 0,
                'kontraktor' => 0,
                'lainnya' => 0,
                'keterangan' => '',
                'sort_order' => 0,
            ],
            [
                'id' => null,
                'no_urut' => 2,
                'tanggal' => sprintf('2/%d/%d', $month, $year),
                'jumlah_kehadiran_tamu' => 0,
                'tamu_pln' => 0,
                'instansi' => 0,
                'kontraktor' => 0,
                'lainnya' => 0,
                'keterangan' => '',
                'sort_order' => 1,
            ],
            [
                'id' => null,
                'no_urut' => 3,
                'tanggal' => sprintf('3/%d/%d', $month, $year),
                'jumlah_kehadiran_tamu' => 0,
                'tamu_pln' => 0,
                'instansi' => 0,
                'kontraktor' => 0,
                'lainnya' => 0,
                'keterangan' => '',
                'sort_order' => 2,
            ],
        ];
    }

    /**
     * Build sample rows matching the official scanned document SMT-FM-AK3-06.04.
     *
     * @return list<array{id: null, no_urut: int, tanggal: string, jumlah_kehadiran_tamu: int, tamu_pln: int, instansi: int, kontraktor: int, lainnya: int, keterangan: string, sort_order: int}>
     */
    public static function buildSampleRows(int $year, int $month): array
    {
        $samples = [
            ['day' => 1, 'jumlah' => 1, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 1, 'ket' => 'SERVICE AC'],
            ['day' => 3, 'jumlah' => 2, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 2, 'lainnya' => 0, 'ket' => "PT RGP\nPT. ANS"],
            ['day' => 4, 'jumlah' => 2, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 2, 'lainnya' => 0, 'ket' => "PT. SUCOFINDO\nSURVEY JAKARTA"],
            ['day' => 7, 'jumlah' => 1, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 1, 'ket' => 'WIRA KENDARI'],
            ['day' => 10, 'jumlah' => 2, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 1, 'lainnya' => 1, 'ket' => "PT KASIROMUA\nCV ALIF PRATAMA"],
            ['day' => 13, 'jumlah' => 3, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 1, 'lainnya' => 1, 'ket' => "PT. ALIV PRATAMA\nPT. NUSANTARA ENGENERING"],
            ['day' => 14, 'jumlah' => 3, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 3, 'ket' => "CV IRVEL TEHNIK\nKSO NEE-PEG-GKP\nCV IRVEL TEHNIK"],
            ['day' => 15, 'jumlah' => 3, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 1, 'ket' => 'KSO NEE-PEG-GKP'],
            ['day' => 18, 'jumlah' => 5, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 1, 'ket' => "KSO NEE-PEG-GKP\nSMKN 2 KENDARI\nPT. SUCOFINDO"],
            ['day' => 19, 'jumlah' => 2, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 1, 'lainnya' => 1, 'ket' => "CV. IRVEL TEHNIK\nPT. KB"],
            ['day' => 20, 'jumlah' => 1, 'pln' => 1, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 0, 'ket' => 'PT PLN NPS'],
            ['day' => 24, 'jumlah' => 1, 'pln' => 1, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 0, 'ket' => 'PT PLN NPS'],
            ['day' => 27, 'jumlah' => 1, 'pln' => 0, 'instansi' => 0, 'kontraktor' => 1, 'lainnya' => 0, 'ket' => 'PT. ALTRAK'],
            ['day' => 28, 'jumlah' => 4, 'pln' => 0, 'instansi' => 1, 'kontraktor' => 1, 'lainnya' => 0, 'ket' => "PT. ALTRAK\nUNIVERSITAS HALUOLEO (UHO)"],
            ['day' => 30, 'jumlah' => 1, 'pln' => 1, 'instansi' => 0, 'kontraktor' => 0, 'lainnya' => 0, 'ket' => 'PT PLN NPS'],
        ];

        return array_map(fn (array $item, int $idx): array => [
            'id' => null,
            'no_urut' => $idx + 1,
            'tanggal' => sprintf('%d/%d/%d', $item['day'], $month, $year),
            'jumlah_kehadiran_tamu' => $item['jumlah'],
            'tamu_pln' => $item['pln'],
            'instansi' => $item['instansi'],
            'kontraktor' => $item['kontraktor'],
            'lainnya' => $item['lainnya'],
            'keterangan' => $item['ket'],
            'sort_order' => $idx,
        ], $samples, array_keys($samples));
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
        return $this->hasMany(K3PengusahaanBukuTamuItem::class, 'laporan_id')->orderBy('sort_order')->orderBy('no_urut');
    }
}
