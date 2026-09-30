<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\OperasiInstruksiKerjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An Instruksi Kerja (IK) Operasi of a unit (same structure as {@see HarInstruksiKerja}). `sections` are its parts:
 * numbered (1. ALAT) or a bold sub-heading (PERSIAPAN AWAL …), each with a
 * point style, an optional opening sentence and points that may carry
 * sub-points; numbering can continue from the previous part.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $sort_order
 * @property string $kop
 * @property string $judul
 * @property string|null $mesin
 * @property string|null $no_dokumen
 * @property Carbon|null $tanggal
 * @property string|null $revisi
 * @property list<array{judul: string, bernomor: bool, gaya: string, lanjut: bool, pengantar: string, butir: list<array{teks: string, sub: list<string>}>}> $sections
 * @property string|null $dibuat_jabatan
 * @property string|null $dibuat_nama
 * @property string|null $disetujui_jabatan
 * @property string|null $disetujui_nama
 */
#[Fillable([
    'unit_id', 'sort_order', 'kop', 'judul', 'mesin', 'no_dokumen', 'tanggal', 'revisi', 'sections',
    'dibuat_jabatan', 'dibuat_nama', 'disetujui_jabatan', 'disetujui_nama', 'input_by',
])]
class OperasiInstruksiKerja extends Model
{
    /** @use HasFactory<OperasiInstruksiKerjaFactory> */
    use BelongsToUnit, HasFactory;

    /** Point styles: 1. 2. 3. / a. b. c. / • / ➢ / – / plain paragraphs. */
    public const STYLES = HarInstruksiKerja::STYLES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'sections' => 'array',
        ];
    }
}
