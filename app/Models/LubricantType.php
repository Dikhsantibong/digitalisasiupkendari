<?php

namespace App\Models;

use App\Enums\LubricantUnit;
use App\Models\Concerns\BelongsToUnit;
use Database\Factories\LubricantTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Per-unit lubricant type master.
 *
 * @property int $id
 * @property int $unit_id
 * @property string|null $code
 * @property string $name
 * @property LubricantUnit $unit_of_measure
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'code', 'name', 'unit_of_measure', 'sort_order', 'is_active'])]
class LubricantType extends Model
{
    /** @use HasFactory<LubricantTypeFactory> */
    use BelongsToUnit, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_of_measure' => LubricantUnit::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Machine, $this>
     */
    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'machine_lubricant_type');
    }
}
