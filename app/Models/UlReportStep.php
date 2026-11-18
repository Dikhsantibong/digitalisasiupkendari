<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One approver of a Laporan UL, frozen to the employee holding the jabatan
 * when the report was submitted: a TL (Menyetujui) or the Manager UL
 * (Mengesahkan, the last step).
 *
 * @property int $id
 * @property int $ul_report_document_id
 * @property int $sequence
 * @property string $caption
 * @property string $position
 * @property int|null $employee_id
 * @property int|null $signed_by
 * @property Carbon|null $signed_at
 * @property string|null $note
 * @property-read Employee|null $employee
 * @property-read User|null $signer
 * @property-read UlReportDocument $document
 */
#[Fillable(['ul_report_document_id', 'sequence', 'caption', 'position', 'employee_id', 'signed_by', 'signed_at', 'note'])]
class UlReportStep extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UlReportDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(UlReportDocument::class, 'ul_report_document_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
