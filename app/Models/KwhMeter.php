<?php

namespace App\Models;

use App\Enums\KwhMeterKategori;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\KwhMeterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-unit kWh meter master read by the Opname kWh.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property KwhMeterKategori $kategori
 * @property int|null $machine_id
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Machine|null $machine
 */
#[Fillable(['unit_id', 'name', 'kategori', 'machine_id', 'sort_order', 'is_active'])]
class KwhMeter extends Model
{
    /** @use HasFactory<KwhMeterFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => KwhMeterKategori::class,
            'machine_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
