<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A supplier (toko / tempat pembelian) of a UL's Master Supplier — its name,
 * e-mail, NPWP and KTP photo go automatically into the lampiran of every
 * cash card row bought there (user, 2026-11-20).
 *
 * @property int $id
 * @property int $service_unit_id
 * @property string $name
 * @property string|null $email
 * @property string|null $npwp
 * @property string|null $ktp_path public-disk path of the KTP photo
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ServiceUnit $serviceUnit
 */
#[Fillable(['service_unit_id', 'name', 'email', 'npwp', 'ktp_path', 'is_active'])]
class Supplier extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_unit_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ServiceUnit, $this>
     */
    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class);
    }

    public function ktpUrl(): ?string
    {
        return $this->ktp_path !== null && Storage::disk('public')->exists($this->ktp_path)
            ? Storage::disk('public')->url($this->ktp_path)
            : null;
    }
}
