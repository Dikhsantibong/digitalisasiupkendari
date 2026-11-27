<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal Pelaksanaan 5S5R (OPERASI) — rencana/realisasi harian per pelaksana
 * (diambil dari operator tiap shift/regu maupun pelaksana custom).
 */
#[Fillable(['unit_id', 'year', 'month', 'no_urut', 'employee_id', 'shift', 'pelaksana', 'rencana', 'realisasi', 'target', 'sort_order', 'input_by'])]
class Operasi5s5rJadwal extends Model
{
    use BelongsToUnit, HasFactory;

    protected $table = 'operasi_5s5r_jadwals';

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Display label for consolidated reports: appends "(Shift X)" when a shift
     * is set and not already included in the pelaksana text.
     */
    public function displayPelaksana(): string
    {
        $name = trim((string) $this->pelaksana);
        $shift = trim((string) ($this->shift ?? ''));

        if ($shift === '' || stripos($name, "shift {$shift}") !== false || stripos($name, "regu {$shift}") !== false) {
            return $name;
        }

        return "{$name} (Shift {$shift})";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'no_urut' => 'integer',
            'employee_id' => 'integer',
            'rencana' => 'array',
            'realisasi' => 'array',
            'target' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
