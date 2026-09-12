<?php

namespace App\Models;

use App\Enums\AttendanceCodeType;
use Database\Factories\AttendanceCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A global attendance / shift code (P, S, M, OFF, C, SKT, I, A).
 *
 * @property int $id
 * @property string $code
 * @property string $label
 * @property AttendanceCodeType $type
 * @property string|null $jam_mulai
 * @property string|null $jam_selesai
 * @property bool $hitung_hadir
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable([
    'code', 'label', 'type', 'jam_mulai', 'jam_selesai', 'hitung_hadir',
    'sort_order', 'is_active',
])]
class AttendanceCode extends Model
{
    /** @use HasFactory<AttendanceCodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttendanceCodeType::class,
            'hitung_hadir' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
