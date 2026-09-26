<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperasiChecklistCommissioningMesin extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'rows' => 'array',
        ];
    }

    public const STATUS_OPTIONS = [
        'ON' => 'ON',
        'OF' => 'OF',
        'SIAP OPERASI' => 'SIAP OPERASI',
        'ABNORMAL' => 'ABNORMAL',
        'TIDAK SIAP OPERASI' => 'TIDAK SIAP OPERASI',
    ];

    public const DEFAULT_ROWS = [
        [
            'no' => 1,
            'section' => 'PERSIAPAN',
            'kegiatan' => 'Periksa dan pastikan semua PMT Feeder, Generator dan coupling dalam posisi OPEN',
            'status' => 'ON',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 2,
            'section' => 'PERSIAPAN',
            'kegiatan' => 'Periksa dan pastikan saklar alat bantu semua mesin pada posisi OFF atau AUTO',
            'status' => 'ON',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 3,
            'section' => 'PERSIAPAN',
            'kegiatan' => 'Pastikan kondisi PMT Trafo dan PMT Generator dalam kondisi OPEN',
            'status' => 'SIAP OPERASI',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 7,
            'section' => 'PARAREL GENERATOR',
            'kegiatan' => 'Operasikan salah satu mesin Cummins',
            'status' => 'SIAP OPERASI',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 1,
            'section' => 'PARAREL GENERATOR',
            'kegiatan' => 'Tegangan nominal generator terpenuhi dan bisa diatur melalui voltage regulator',
            'status' => 'SIAP OPERASI',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 2,
            'section' => 'PARAREL GENERATOR',
            'kegiatan' => 'Pararel generator',
            'status' => 'SIAP OPERASI',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 3,
            'section' => 'PARAREL GENERATOR',
            'kegiatan' => 'Atur beban dasar',
            'status' => 'ON',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 4,
            'section' => 'PARAREL GENERATOR',
            'kegiatan' => 'Atur cos phi',
            'status' => 'ON',
            'pic' => '',
            'paraf' => '',
        ],
        [
            'no' => 5,
            'section' => 'PARAREL GENERATOR',
            'kegiatan' => 'UPB Kendari Koordinasi dengan piket CCR agar ON PMT salah satu Feeder',
            'status' => 'SIAP OPERASI',
            'pic' => '',
            'paraf' => '',
        ],
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
