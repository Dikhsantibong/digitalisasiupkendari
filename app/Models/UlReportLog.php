<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One action in the approval history of a Laporan UL (ajukan, setujui,
 * sahkan, tolak).
 *
 * @property int $id
 * @property int $ul_report_document_id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $jabatan
 * @property string $action
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $note
 * @property Carbon|null $created_at
 */
#[Fillable(['ul_report_document_id', 'user_id', 'user_name', 'jabatan', 'action', 'from_status', 'to_status', 'note', 'created_at'])]
class UlReportLog extends Model
{
    public const UPDATED_AT = null;
}
