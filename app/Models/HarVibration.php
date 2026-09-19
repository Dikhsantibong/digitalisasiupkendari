<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarVibration extends Model
{
    use HasFactory;

    protected $table = 'har_vibrations';

    protected $fillable = [
        'unit_id',
        'machine_id',
        'test_date',
        'document_number',
        'revision',
        'effective_date',
        'brand',
        'model_type',
        'installed_power',
        'capable_power',
        'serial_number',
        'machine_number',
        'rpm',
        'measurements',
        'standard_text',
        'max_text',
        'conclusion_text',
        'manager_ul_id',
        'manager_ul_name',
        'manager_ul_title',
        'tl_har_id',
        'tl_har_name',
        'tl_har_title',
        'staff_har_id',
        'staff_har_name',
        'staff_har_title',
        'page_margin_top',
        'page_margin_bottom',
        'page_margin_left',
        'page_margin_right',
        'line_spacing',
        'format',
        'content_html',
        'created_by',
    ];

    protected $casts = [
        'test_date' => 'date',
        'measurements' => 'array',
        'page_margin_top' => 'integer',
        'page_margin_bottom' => 'integer',
        'page_margin_left' => 'integer',
        'page_margin_right' => 'integer',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function managerUl(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_ul_id');
    }

    public function tlHar(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'tl_har_id');
    }

    public function staffHar(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'staff_har_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Standard 16 measurement points template.
     */
    public static function defaultPoints(): array
    {
        return [
            ['pos' => 1, 'point' => 'A1'],
            ['pos' => 2, 'point' => 'A2'],
            ['pos' => 3, 'point' => 'B1'],
            ['pos' => 4, 'point' => 'B2'],
            ['pos' => 5, 'point' => 'C1'],
            ['pos' => 6, 'point' => 'C2'],
            ['pos' => 7, 'point' => 'D1'],
            ['pos' => 8, 'point' => 'D2'],
            ['pos' => 9, 'point' => 'E1'],
            ['pos' => 10, 'point' => 'E2'],
            ['pos' => 11, 'point' => 'F1'],
            ['pos' => 12, 'point' => 'F2'],
            ['pos' => 13, 'point' => 'G1'],
            ['pos' => 14, 'point' => 'G2'],
            ['pos' => 15, 'point' => 'G3'],
            ['pos' => 16, 'point' => 'Governor Side'],
        ];
    }

    public static function defaultMeasurements(): array
    {
        $rows = [];
        foreach (self::defaultPoints() as $item) {
            $rows[] = [
                'pos' => $item['pos'],
                'point' => $item['point'],
                'v_max' => '',
                'v_min' => '',
                'v_avg' => '',
                'h_max' => '',
                'h_min' => '',
                'h_avg' => '',
                'notes' => '',
            ];
        }

        return $rows;
    }

    /**
     * Measurement values extracted directly from official physical document scan.
     */
    public static function sampleScanMeasurements(): array
    {
        return [
            ['pos' => 1, 'point' => 'A1', 'v_max' => '1,60', 'v_min' => '1,60', 'v_avg' => '1,60', 'h_max' => '2,70', 'h_min' => '2,70', 'h_avg' => '2,70', 'notes' => ''],
            ['pos' => 2, 'point' => 'A2', 'v_max' => '1,80', 'v_min' => '1,70', 'v_avg' => '1,75', 'h_max' => '2,90', 'h_min' => '2,90', 'h_avg' => '2,90', 'notes' => ''],
            ['pos' => 3, 'point' => 'B1', 'v_max' => '1,20', 'v_min' => '1,00', 'v_avg' => '1,10', 'h_max' => '1,20', 'h_min' => '1,20', 'h_avg' => '1,20', 'notes' => ''],
            ['pos' => 4, 'point' => 'B2', 'v_max' => '3,00', 'v_min' => '2,90', 'v_avg' => '2,95', 'h_max' => '1,40', 'h_min' => '1,40', 'h_avg' => '1,40', 'notes' => ''],
            ['pos' => 5, 'point' => 'C1', 'v_max' => '3,20', 'v_min' => '2,80', 'v_avg' => '3,00', 'h_max' => '2,90', 'h_min' => '2,80', 'h_avg' => '2,85', 'notes' => ''],
            ['pos' => 6, 'point' => 'C2', 'v_max' => '2,60', 'v_min' => '2,40', 'v_avg' => '2,50', 'h_max' => '3,00', 'h_min' => '2,90', 'h_avg' => '2,95', 'notes' => ''],
            ['pos' => 7, 'point' => 'D1', 'v_max' => '3,00', 'v_min' => '2,90', 'v_avg' => '2,95', 'h_max' => '5,00', 'h_min' => '4,90', 'h_avg' => '4,95', 'notes' => ''],
            ['pos' => 8, 'point' => 'D2', 'v_max' => '2,90', 'v_min' => '2,90', 'v_avg' => '2,90', 'h_max' => '4,60', 'h_min' => '4,60', 'h_avg' => '4,60', 'notes' => ''],
            ['pos' => 9, 'point' => 'E1', 'v_max' => '2,00', 'v_min' => '2,00', 'v_avg' => '2,00', 'h_max' => '3,80', 'h_min' => '3,80', 'h_avg' => '3,80', 'notes' => ''],
            ['pos' => 10, 'point' => 'E2', 'v_max' => '2,50', 'v_min' => '2,30', 'v_avg' => '2,40', 'h_max' => '4,10', 'h_min' => '4,00', 'h_avg' => '4,05', 'notes' => ''],
            ['pos' => 11, 'point' => 'F1', 'v_max' => '2,70', 'v_min' => '2,70', 'v_avg' => '2,70', 'h_max' => '5,40', 'h_min' => '5,40', 'h_avg' => '5,40', 'notes' => ''],
            ['pos' => 12, 'point' => 'F2', 'v_max' => '2,60', 'v_min' => '2,60', 'v_avg' => '2,60', 'h_max' => '5,70', 'h_min' => '5,70', 'h_avg' => '5,70', 'notes' => ''],
            ['pos' => 13, 'point' => 'G1', 'v_max' => '', 'v_min' => '', 'v_avg' => '', 'h_max' => '14,80', 'h_min' => '14,70', 'h_avg' => '14,75', 'notes' => ''],
            ['pos' => 14, 'point' => 'G2', 'v_max' => '', 'v_min' => '', 'v_avg' => '', 'h_max' => '16,20', 'h_min' => '16,10', 'h_avg' => '16,15', 'notes' => ''],
            ['pos' => 15, 'point' => 'G3', 'v_max' => '', 'v_min' => '', 'v_avg' => '', 'h_max' => '14,40', 'h_min' => '14,20', 'h_avg' => '14,30', 'notes' => ''],
            ['pos' => 16, 'point' => 'Governor Side', 'v_max' => '', 'v_min' => '', 'v_avg' => '', 'h_max' => '', 'h_min' => '', 'h_avg' => '', 'notes' => ''],
        ];
    }
}
