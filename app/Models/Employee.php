<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee (pegawai) attached to a generating unit.
 *
 * @property int $id
 * @property int|null $unit_id
 * @property int|null $service_unit_id
 * @property string $name
 * @property string|null $nip
 * @property string|null $position
 * @property string|null $signature_path
 * @property string|null $regu
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit|null $unit
 * @property-read ServiceUnit|null $serviceUnit
 */
#[Fillable([
    'unit_id',
    'service_unit_id',
    'name',
    'nip',
    'position',
    'signature_path',
    'regu',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public function signatureUrl(): ?string
    {
        return $this->signature_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->signature_path) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<ServiceUnit, $this>
     */
    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class);
    }

    /**
     * Limit the query to the employees whose unit or service unit the given user may see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasGlobalAccess()) {
            return;
        }

        $query->where(function (Builder $q) use ($user): void {
            $q->whereIn('unit_id', $user->accessibleUnitIds())
                ->orWhereIn('service_unit_id', $user->accessibleServiceUnitIds());
        });
    }
}
