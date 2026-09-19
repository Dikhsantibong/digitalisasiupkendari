<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use App\Support\PdmSampleMonitoringForm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A row of the sample monitoring form. `section` is pengiriman (A), hasil (B)
 * or temuan (D); `data` holds that section's columns as defined in
 * {@see PdmSampleMonitoringForm}.
 *
 * @property int $id
 * @property int $pdm_sample_monitoring_id
 * @property int $unit_id
 * @property string $section
 * @property array<string, string|null> $data
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PdmSampleMonitoring $monitoring
 */
#[Fillable(['pdm_sample_monitoring_id', 'unit_id', 'section', 'data', 'sort_order'])]
class PdmSampleMonitoringItem extends Model
{
    use BelongsToUnit;

    protected $table = 'pdm_sample_monitoring_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'data' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PdmSampleMonitoring, $this>
     */
    public function monitoring(): BelongsTo
    {
        return $this->belongsTo(PdmSampleMonitoring::class, 'pdm_sample_monitoring_id');
    }
}
