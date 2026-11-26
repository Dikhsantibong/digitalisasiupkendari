<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class K3PengusahaanPatrolSecurity extends Model
{
    use HasFactory;

    protected $table = 'k3_pengusahaan_patrol_securities';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'judul',
        'catatan',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'input_by' => 'integer',
        ];
    }

    /**
     * Generate default checkpoints for the unit from its PatrolLocation master records,
     * falling back to 14 generated checkpoints (e.g. POA1..POA14 for Poasia) when none exist.
     *
     * @return list<array{lokasi_kode: string, lokasi_nama: string, scans: array<string, int>, total: int, persentase: float, sort_order: int}>
     */
    public static function defaultCheckpoints(Unit $unit, int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $emptyScans = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $emptyScans[(string) $d] = 0;
        }

        $masterLocations = PatrolLocation::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        if ($masterLocations->isNotEmpty()) {
            return $masterLocations->values()->map(fn (PatrolLocation $loc, int $idx): array => [
                'lokasi_kode' => $loc->code,
                'lokasi_nama' => $loc->name ?: $loc->code,
                'scans' => $emptyScans,
                'total' => 0,
                'persentase' => 0.0,
                'sort_order' => $idx,
            ])->all();
        }

        $prefix = self::patrolPrefix($unit);
        $checkpoints = [];
        for ($i = 1; $i <= 14; $i++) {
            $checkpoints[] = [
                'lokasi_kode' => $prefix.$i,
                'lokasi_nama' => $prefix.$i,
                'scans' => $emptyScans,
                'total' => 0,
                'persentase' => 0.0,
                'sort_order' => $i - 1,
            ];
        }

        return $checkpoints;
    }

    public static function patrolPrefix(Unit $unit): string
    {
        $name = preg_replace('/^PLT[DGMU]\s*/i', '', (string) ($unit->name ?? $unit->code ?? ''));
        $alnum = strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) $name));

        return substr($alnum, 0, 3) ?: 'POA';
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(K3PengusahaanPatrolSecurityItem::class, 'laporan_id')->orderBy('sort_order')->orderBy('id');
    }
}
