<?php

namespace App\Models;

use App\Enums\EmployeePosition;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An employee (pegawai) attached to a generating unit.
 *
 * @property int $id
 * @property int|null $unit_id
 * @property int|null $service_unit_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $nip
 * @property string|null $position
 * @property string|null $division
 * @property string|null $singleton_key
 * @property string|null $signature_path
 * @property string|null $regu
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit|null $unit
 * @property-read ServiceUnit|null $serviceUnit
 * @property-read User|null $user
 */
#[Fillable([
    'unit_id',
    'service_unit_id',
    'user_id',
    'name',
    'nip',
    'position',
    'division',
    'signature_path',
    'regu',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            $employee->forceFill(self::signerAttributes(
                $employee->position,
                $employee->unit_id,
                $employee->service_unit_id,
                (bool) ($employee->is_active ?? true),
                $employee->division,
            ));
        });
    }

    /**
     * The divisi and the one-active-holder-per-unit key derived from a
     * canonical report-signer jabatan ({@see EmployeePosition}); a free-text
     * jabatan keeps its divisi and has no key.
     *
     * @return array{division: string|null, singleton_key: string|null}
     */
    public static function signerAttributes(?string $position, ?int $unitId, ?int $serviceUnitId, bool $isActive, ?string $division = null): array
    {
        $canonical = EmployeePosition::tryFrom((string) $position);

        return [
            'division' => $canonical?->division() ?? $division,
            'singleton_key' => $isActive ? $canonical?->singletonKey($unitId, $serviceUnitId) : null,
        ];
    }

    public function canonicalPosition(): ?EmployeePosition
    {
        return EmployeePosition::tryFrom((string) $this->position);
    }

    public function signatureUrl(): ?string
    {
        return $this->signature_path ? Storage::disk('public')->url($this->signature_path) : null;
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
     * The login account of this employee — the only account allowed to sign
     * a report step assigned to this employee.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
