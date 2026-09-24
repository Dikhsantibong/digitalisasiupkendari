<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3MetodePengujianPeralatanMeta extends Model
{
    use HasFactory;

    protected $table = 'k3_metode_pengujian_peralatan_meta';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'catatan',
        'document_number',
        'revision',
        'effective_date',
        'manager_ul_id',
        'manager_ul_name',
        'manager_ul_title',
        'tl_k3_id',
        'tl_k3_name',
        'tl_k3_title',
        'staff_k3_id',
        'staff_k3_name',
        'staff_k3_title',
        'sign_place_date',
        'page_margin_top',
        'page_margin_bottom',
        'page_margin_left',
        'page_margin_right',
        'line_spacing',
        'format',
        'content_html',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_margin_top' => 'integer',
            'page_margin_bottom' => 'integer',
            'page_margin_left' => 'integer',
            'page_margin_right' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
