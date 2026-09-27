<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class K3PengusahaanFireAlarm extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_fire_alarms';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'revisi',
        'tanggal_dokumen',
        'tanggal_periksa',
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

    public const DEFAULT_ITEMS = [
        ['no_urut' => 1, 'lokasi' => 'LANTAI 2 (CCR)', 'kondisi' => 'Baik', 'panel_indikator' => 'Baik', 'keterangan' => null],
        ['no_urut' => 2, 'lokasi' => 'R. PEMBANGKIT (LOKAL)', 'kondisi' => 'Baik', 'panel_indikator' => 'Baik', 'keterangan' => null],
        ['no_urut' => 3, 'lokasi' => 'AREA RADIATOR', 'kondisi' => 'Baik', 'panel_indikator' => 'Baik', 'keterangan' => null],
        ['no_urut' => 4, 'lokasi' => 'KANTOR UNIT', 'kondisi' => 'Baik', 'panel_indikator' => 'Baik', 'keterangan' => null],
        ['no_urut' => 5, 'lokasi' => 'GUDANG MATERIAL', 'kondisi' => 'Baik', 'panel_indikator' => 'Baik', 'keterangan' => null],
        ['no_urut' => 6, 'lokasi' => 'SECURITY', 'kondisi' => 'Baik', 'panel_indikator' => 'Baik', 'keterangan' => null],
    ];

    /**
     * Build the default rows array with empty IDs for Inertia view.
     *
     * @return list<array{id: null, no_urut: int, lokasi: string, tanggal_periksa: string, kondisi: string, panel_indikator: string, keterangan: string, sort_order: int}>
     */
    public static function buildDefaultRows(?string $defaultDate = null): array
    {
        $rows = [];
        foreach (self::DEFAULT_ITEMS as $index => $item) {
            $rows[] = [
                'id' => null,
                'no_urut' => $item['no_urut'],
                'lokasi' => $item['lokasi'],
                'tanggal_periksa' => $defaultDate ?? '',
                'kondisi' => $item['kondisi'],
                'panel_indikator' => $item['panel_indikator'],
                'keterangan' => $item['keterangan'] ?? '',
                'sort_order' => $index,
            ];
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
        return $this->hasMany(K3PengusahaanFireAlarmItem::class, 'laporan_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
