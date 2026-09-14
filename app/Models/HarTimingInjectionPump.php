<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Formulir Checklist Timing Injection Pump.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $machine_id
 * @property Carbon $test_date
 * @property string $document_number
 * @property string $revision
 * @property string $effective_date
 * @property string|null $brand
 * @property string|null $model_type
 * @property string|null $serial_number
 * @property string|null $machine_number
 * @property string|null $installed_power
 * @property string|null $capable_power
 * @property string|null $rpm
 * @property int $cylinders_count
 * @property string|null $standard_allowed
 * @property array<int, array<string, mixed>> $checklist_items
 * @property string|null $notes
 * @property int|null $manager_ul_id
 * @property string|null $manager_ul_name
 * @property string|null $manager_ul_title
 * @property int|null $tl_har_id
 * @property string|null $tl_har_name
 * @property string|null $tl_har_title
 * @property int|null $staff_har_id
 * @property string|null $staff_har_name
 * @property string|null $staff_har_title
 * @property int $page_margin_top
 * @property int $page_margin_bottom
 * @property int $page_margin_left
 * @property int $page_margin_right
 * @property string $line_spacing
 * @property string $format
 * @property string|null $content_html
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Machine $machine
 * @property-read Employee|null $managerUl
 * @property-read Employee|null $tlHar
 * @property-read Employee|null $staffHar
 * @property-read User|null $creator
 */
class HarTimingInjectionPump extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test_date' => 'date',
            'cylinders_count' => 'integer',
            'checklist_items' => 'array',
            'page_margin_top' => 'integer',
            'page_margin_bottom' => 'integer',
            'page_margin_left' => 'integer',
            'page_margin_right' => 'integer',
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
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function managerUl(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_ul_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function tlHar(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'tl_har_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function staffHar(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'staff_har_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to a specific unit.
     *
     * @param Builder<$this> $query
     * @return Builder<$this>
     */
    public function scopeForUnit(Builder $query, int $unitId): Builder
    {
        return $query->where('unit_id', $unitId);
    }
}
