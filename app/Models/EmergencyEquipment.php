<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\EmergencyEquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A piece of emergency facility equipment per unit (Fire Pump, Ambulance, …),
 * used by the emergency-facility readiness checks.
 *
 * @property int $id
 * @property int $unit_id
 * @property string|null $group_name
 * @property string $name
 * @property string|null $location
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['unit_id', 'group_name', 'name', 'location', 'sort_order', 'is_active'])]
class EmergencyEquipment extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<EmergencyEquipmentFactory> */
    use HasFactory;

    /** "Equipment" is uncountable, so pin the pluralised table name. */
    protected $table = 'emergency_equipments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
