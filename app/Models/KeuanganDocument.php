<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The Laporan Administrasi Keuangan of a UL for one month, edited & saved in
 * the document editor (user, 2026-11-20).
 *
 * @property int $id
 * @property int $service_unit_id
 * @property int $month
 * @property int $year
 * @property string $format html|grid
 * @property string|null $content_html
 * @property array<string, mixed>|null $content_grid
 * @property int $content_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['service_unit_id', 'month', 'year', 'format', 'content_html', 'content_grid', 'content_version', 'created_by', 'updated_by'])]
class KeuanganDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_unit_id' => 'integer',
            'month' => 'integer',
            'year' => 'integer',
            'content_grid' => 'array',
            'content_version' => 'integer',
        ];
    }
}
