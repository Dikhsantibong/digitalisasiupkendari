<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class HarLubeQuality extends Model
{
    use HasFactory;

    protected $table = 'har_lube_qualities';

    protected $fillable = [
        'unit_id',
        'machine_id',
        'test_date',
        'document_number',
        'revision',
        'effective_date',
        'page_number',
        'unit_sentral',
        'machine_name',
        'machine_number',
        'serial_number',
        'sample_point',
        'parameters',
        'status_text',
        'standard_text',
        'photo_path',
        'photo_caption',
        'analisa_text',
        'cba_text',
        'rekomendasi_text',
        'signature_location',
        'signature_date',
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
        'parameters' => 'array',
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
     * Standard default parameter row matching PLN NP UPDK Kendari sample scan.
     */
    public static function defaultParameters(?Carbon $date = null): array
    {
        $d = $date ?: Carbon::now();

        return [
            [
                'tanggal' => $d->format('d-M-y'),
                'tbn' => '23,80',
                'water_content' => '974,00',
                'viscosity_40' => '-',
                'viscosity_100' => '-',
                'aw_additive' => '157,00',
                'glycol' => '0,00',
                'nitration' => '0,00',
                'oxidation' => '16,00',
                'soot' => '0,18',
                'sulfation' => '19,10',
                'keterangan' => 'No Alarm',
            ],
        ];
    }

    public static function sampleScanParameters(): array
    {
        return [
            [
                'tanggal' => '28-Aug-26',
                'tbn' => '23,80',
                'water_content' => '974,00',
                'viscosity_40' => '-',
                'viscosity_100' => '-',
                'aw_additive' => '157,00',
                'glycol' => '0,00',
                'nitration' => '0,00',
                'oxidation' => '16,00',
                'soot' => '0,18',
                'sulfation' => '19,10',
                'keterangan' => 'No Alarm',
            ],
        ];
    }

    public static function defaultStatusText(): string
    {
        return '- No Alarm Sign';
    }

    public static function defaultStandardText(): string
    {
        return '- Water Content : 2000 ppm';
    }

    public static function defaultPhotoCaption(): string
    {
        return "28 Agu 2026 15:46:39\n-3°59'46,05188\"S 122°31'50,60101\"E\n154° SE\nKecamatan Wua-Wua\nKota Kendari\nSulawesi Tenggara\nAkurasi 4.1\nIndex number: 2494";
    }

    public static function defaultAnalisaText(): string
    {
        return "- All parameter normal\n- Alat ukur viskositas eror";
    }

    public static function defaultCbaText(): string
    {
        return 'N/A';
    }

    public static function defaultRekomendasiText(): string
    {
        return 'N/A';
    }
}
