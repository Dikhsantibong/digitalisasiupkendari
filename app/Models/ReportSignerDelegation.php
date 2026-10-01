<?php

namespace App\Models;

use App\Enums\EmployeePosition;
use Database\Factories\ReportSignerDelegationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Penanda tangan lintas unit: for one jabatan, the reports of `unit` are
 * signed by the holder of that jabatan at `sourceUnit` (configured by the
 * Super Admin in Administrasi → Penanda Tangan Laporan).
 *
 * @property int $id
 * @property int $unit_id
 * @property EmployeePosition $position
 * @property int $source_unit_id
 * @property list<int>|null $granted_assignment_ids
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 * @property-read Unit $sourceUnit
 */
#[Fillable(['unit_id', 'position', 'source_unit_id', 'granted_assignment_ids', 'created_by'])]
class ReportSignerDelegation extends Model
{
    /** @use HasFactory<ReportSignerDelegationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => EmployeePosition::class,
            'granted_assignment_ids' => 'array',
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
     * @return BelongsTo<Unit, $this>
     */
    public function sourceUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'source_unit_id');
    }
}
