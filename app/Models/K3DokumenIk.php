<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3DokumenIkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Dokumen Instruksi Kerja (IK) K3 Lingkungan Pembangkit of a unit and report
 * period. `sections` are the numbered parts of the IK, each with a heading, a
 * point style (butir • / huruf a. / angka 1. / paragraf), an optional opening
 * sentence and its points.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $year
 * @property int $month
 * @property int $sort_order
 * @property string $sistem
 * @property string $judul
 * @property string|null $no_dokumen
 * @property Carbon|null $tanggal
 * @property string|null $revisi
 * @property string|null $halaman
 * @property list<array{judul: string, gaya: string, pengantar: string, butir: list<string>}> $sections
 */
#[Fillable(['unit_id', 'year', 'month', 'sort_order', 'sistem', 'judul', 'no_dokumen', 'tanggal', 'revisi', 'halaman', 'sections', 'input_by'])]
class K3DokumenIk extends Model
{
    /** @use HasFactory<K3DokumenIkFactory> */
    use BelongsToUnit, HasFactory;

    /** Point styles of a section. */
    public const STYLES = ['butir', 'huruf', 'angka', 'paragraf'];

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
