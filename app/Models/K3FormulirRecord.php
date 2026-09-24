<?php

namespace App\Models;

use Database\Factories\K3FormulirRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu dokumen Formulir K3 berbasis lembar per unit + formulir + periode.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $form
 * @property int $year
 * @property int $month
 * @property int $week
 * @property array{sections?: array<string, list<array<string, string|null>>>, header?: array<string, string|null>} $data
 * @property string|null $catatan
 * @property string $format
 * @property string|null $content_html
 */
class K3FormulirRecord extends Model
{
    /** @use HasFactory<K3FormulirRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'form',
        'year',
        'month',
        'week',
        'data',
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
        'input_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'year' => 'integer',
            'month' => 'integer',
            'week' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
