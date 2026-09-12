<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\FireExtinguisherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An APAR/APAB fire-extinguisher unit (per tube, identified by RFID), used by
 * the periodic extinguisher inspections.
 *
 * @property int $id
 * @property int $unit_id
 * @property string|null $rfid
 * @property string|null $location
 * @property string|null $merk
 * @property string|null $jenis
 * @property float|null $berat_kg
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'rfid', 'location', 'merk', 'jenis', 'berat_kg', 'sort_order', 'is_active'])]
class FireExtinguisher extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<FireExtinguisherFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'berat_kg' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
