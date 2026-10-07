<?php

namespace App\Models;

use App\Enums\FuelType;
use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A physical generating machine that belongs to a generating unit.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property string|null $merk
 * @property string|null $type
 * @property FuelType|null $fuel_type
 * @property string|null $serial_number
 * @property string|null $engine_hp
 * @property int|null $engine_rpm
 * @property int|null $tahun_pembuatan
 * @property string|null $generator_merk
 * @property string|null $generator_type
 * @property string|null $generator_serial_number
 * @property int|null $generator_volt
 * @property string|null $generator_kva
 * @property string|null $generator_cos_phi
 * @property string|null $capacity_kw
 * @property string $kwh_faktor_kali_produksi
 * @property string $kwh_faktor_kali_ps
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Collection<int, LubricantType> $lubricantTypes
 */
#[Fillable([
    'unit_id',
    'name',
    'merk',
    'type',
    'fuel_type',
    'serial_number',
    'engine_hp',
    'engine_rpm',
    'tahun_pembuatan',
    'generator_merk',
    'generator_type',
    'generator_serial_number',
    'generator_volt',
    'generator_kva',
    'generator_cos_phi',
    'capacity_kw',
    'kwh_faktor_kali_produksi',
    'kwh_faktor_kali_ps',
    'is_active',
])]
class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fuel_type' => FuelType::class,
            'kwh_faktor_kali_produksi' => 'decimal:4',
            'kwh_faktor_kali_ps' => 'decimal:4',
            'engine_rpm' => 'integer',
            'tahun_pembuatan' => 'integer',
            'generator_volt' => 'integer',
            'generator_kva' => 'decimal:2',
            'generator_cos_phi' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The lubricant types this machine uses. The operator completes this per
     * machine — the source Excel never modelled it.
     *
     * @return BelongsToMany<LubricantType, $this>
     */
    public function lubricantTypes(): BelongsToMany
    {
        return $this->belongsToMany(LubricantType::class, 'machine_lubricant_type');
    }

    /**
     * Limit the query to the machines whose unit the given user may see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasGlobalAccess()) {
            return;
        }

        $query->whereIn('unit_id', $user->accessibleUnitIds());
    }
}
