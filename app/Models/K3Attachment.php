<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A monthly K3 attachment (document/photo) stored on disk; only the path here.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property string $title
 * @property string $file_path
 * @property string|null $category
 */
#[Fillable(['unit_id', 'year', 'month', 'title', 'file_path', 'category', 'input_by'])]
class K3Attachment extends Model
{
    use BelongsToUnit;
}
