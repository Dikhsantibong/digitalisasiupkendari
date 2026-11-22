<?php

namespace App\Models;

use App\Enums\PlantType;
use Database\Factories\LogsheetParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A logsheet column definition (what the operator reads), part of a
 * {@see LogsheetTemplate} (the form of a jenis mesin). The header has up to
 * three levels: group_label (e.g. "Temperature Stud Main Bearing") → name
 * ("Bank A") → sub_channel ("1"). `input_type` is number (stored in `value`),
 * text or choice (stored in `value_text`, choice from `options`).
 * Normal / Min-Alarm / Max-Trip are reference texts printed under the header.
 *
 * @property int $id
 * @property int|null $template_id
 * @property PlantType $plant_type
 * @property string $code
 * @property string|null $group_label
 * @property string $name
 * @property string|null $unit_of_measure
 * @property string|null $sub_channel
 * @property string $input_type
 * @property list<string>|null $options
 * @property string|null $normal_value
 * @property string|null $alarm_value
 * @property string|null $trip_value
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['template_id', 'plant_type', 'code', 'group_label', 'name', 'unit_of_measure', 'sub_channel', 'input_type', 'options', 'normal_value', 'alarm_value', 'trip_value', 'sort_order', 'is_active'])]
class LogsheetParameter extends Model
{
    /** @use HasFactory<LogsheetParameterFactory> */
    use HasFactory;

    public const INPUT_TYPES = ['number', 'text', 'choice'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plant_type' => PlantType::class,
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LogsheetTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LogsheetTemplate::class, 'template_id');
    }

    /**
     * The parameters of a template; rows without a template belong to the default one.
     *
     * @param  Builder<LogsheetParameter>  $query
     */
    public function scopeOfTemplate(Builder $query, ?LogsheetTemplate $template): void
    {
        $query->where(function (Builder $q) use ($template): void {
            if ($template === null) {
                $q->whereNull('template_id');

                return;
            }
            $q->where('template_id', $template->id);
            if ($template->is_default) {
                $q->orWhereNull('template_id');
            }
        });
    }

    public function isNumeric(): bool
    {
        return ($this->input_type ?: 'number') === 'number';
    }

    /** The column label, appending the sub-channel (e.g. "Ampere R"). */
    public function label(): string
    {
        return $this->sub_channel ? "{$this->name} {$this->sub_channel}" : $this->name;
    }

    /** The label with its group (e.g. "Temperature Stud Main Bearing Bank A 1"). */
    public function fullLabel(): string
    {
        return $this->group_label ? "{$this->group_label} {$this->label()}" : $this->label();
    }
}
