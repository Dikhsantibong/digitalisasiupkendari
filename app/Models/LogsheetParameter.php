<?php

namespace App\Models;

use App\Enums\PlantType;
use Database\Factories\LogsheetParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A logsheet column definition (what the operator reads). Global master; a
 * `plant_type` other than `all` lets PLTM/PLTG carry their own set later.
 *
 * @property int $id
 * @property PlantType $plant_type
 * @property string $code
 * @property string $name
 * @property string|null $unit_of_measure
 * @property string|null $sub_channel
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['plant_type', 'code', 'name', 'unit_of_measure', 'sub_channel', 'sort_order', 'is_active'])]
class LogsheetParameter extends Model
{
    /** @use HasFactory<LogsheetParameterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plant_type' => PlantType::class,
            'is_active' => 'boolean',
        ];
    }

    /** The column label, appending the sub-channel (e.g. "Ampere R"). */
    public function label(): string
    {
        return $this->sub_channel ? "{$this->name} {$this->sub_channel}" : $this->name;
    }
}
