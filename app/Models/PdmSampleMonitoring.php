<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\PdmSampleMonitoringFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Header of the PdM "Form Monitoring Pemeriksaan & Pengiriman Sample" for one
 * unit & period; its rows (sections A, B, D) are {@see PdmSampleMonitoringItem}.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string|null $lokasi
 * @property string|null $pic_monitoring
 * @property array<string, array{target: int|null, keterangan: string|null}>|null $rekap_targets
 * @property string|null $catatan
 * @property int|null $input_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Collection<int, PdmSampleMonitoringItem> $items
 */
#[Fillable(['unit_id', 'year', 'month', 'lokasi', 'pic_monitoring', 'rekap_targets', 'catatan', 'input_by'])]
class PdmSampleMonitoring extends Model
{
    /** @use HasFactory<PdmSampleMonitoringFactory> */
    use BelongsToUnit, HasFactory;

    protected $table = 'pdm_sample_monitorings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'rekap_targets' => 'array',
        ];
    }

    /**
     * @return HasMany<PdmSampleMonitoringItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PdmSampleMonitoringItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
