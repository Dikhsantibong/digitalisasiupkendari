<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The Laporan Realisasi Cash Card of a UL for one month (user, 2026-11-20):
 * the saldo awal and its rows ({@see CashCardEntry}).
 *
 * @property int $id
 * @property int $service_unit_id
 * @property int $month
 * @property int $year
 * @property string $saldo_awal
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ServiceUnit $serviceUnit
 */
#[Fillable(['service_unit_id', 'month', 'year', 'saldo_awal', 'created_by', 'updated_by'])]
class CashCardReport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_unit_id' => 'integer',
            'month' => 'integer',
            'year' => 'integer',
            'saldo_awal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ServiceUnit, $this>
     */
    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class);
    }

    /**
     * @return HasMany<CashCardEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CashCardEntry::class)->orderBy('sort_order')->orderBy('id');
    }
}
