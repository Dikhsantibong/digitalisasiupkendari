<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inspeksi Fire Alarm per unit, bulan, dan tahun.
 */
#[Fillable([
    'unit_id', 'year', 'month', 'no_urut', 'lokasi', 'tanggal_periksa',
    'kondisi', 'panel_indikator', 'keterangan', 'sort_order', 'input_by',
])]
class K3FireAlarmInspection extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'k3_fire_alarm_inspections';

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
            'tanggal_periksa' => 'date:Y-m-d',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
