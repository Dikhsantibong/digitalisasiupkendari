<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanJamKerjaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanJamKerja extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<K3PengusahaanJamKerjaFactory> */
    use HasFactory;

    protected $table = 'k3_pengusahaan_jam_kerjas';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'tgl_berlaku',
        'revisi',
        'halaman',
        'karyawan_tetap',
        'karyawan_tetap_shift',
        'karyawan_tidak_tetap',
        'karyawan_tidak_tetap_shift',
        'hari_tetap',
        'jam_tetap',
        'lembur_tetap',
        'hari_tetap_shift',
        'jam_tetap_shift',
        'lembur_tetap_shift',
        'hari_tidak_tetap',
        'jam_tidak_tetap',
        'lembur_tidak_tetap',
        'hari_tidak_tetap_shift',
        'jam_tidak_tetap_shift',
        'lembur_tidak_tetap_shift',
        'cuti_orang',
        'cuti_hari',
        'cuti_jam',
        'ijin_orang',
        'ijin_hari',
        'ijin_jam',
        'sakit_orang',
        'sakit_hari',
        'sakit_jam',
        'total_jam_kerja_orang',
        'total_lembur',
        'total_absensi_jam',
        'total_jam_kerja_seluruh',
        'catatan',
        'input_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'karyawan_tetap' => 'integer',
            'karyawan_tetap_shift' => 'integer',
            'karyawan_tidak_tetap' => 'integer',
            'karyawan_tidak_tetap_shift' => 'integer',
            'hari_tetap' => 'integer',
            'jam_tetap' => 'float',
            'lembur_tetap' => 'float',
            'hari_tetap_shift' => 'integer',
            'jam_tetap_shift' => 'float',
            'lembur_tetap_shift' => 'float',
            'hari_tidak_tetap' => 'integer',
            'jam_tidak_tetap' => 'float',
            'lembur_tidak_tetap' => 'float',
            'hari_tidak_tetap_shift' => 'integer',
            'jam_tidak_tetap_shift' => 'float',
            'lembur_tidak_tetap_shift' => 'float',
            'cuti_orang' => 'integer',
            'cuti_hari' => 'integer',
            'cuti_jam' => 'float',
            'ijin_orang' => 'integer',
            'ijin_hari' => 'integer',
            'ijin_jam' => 'float',
            'sakit_orang' => 'integer',
            'sakit_hari' => 'integer',
            'sakit_jam' => 'float',
            'total_jam_kerja_orang' => 'float',
            'total_lembur' => 'float',
            'total_absensi_jam' => 'float',
            'total_jam_kerja_seluruh' => 'float',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inputUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * Compute totals dynamically based on inputs.
     *
     * @param  array<string, mixed>  $data
     * @return array{total_jam_kerja_orang: float, total_lembur: float, total_absensi_jam: float, total_jam_kerja_seluruh: float}
     */
    public static function computeTotals(array $data): array
    {
        $jamTetap = (int) ($data['karyawan_tetap'] ?? 0) * (int) ($data['hari_tetap'] ?? 0) * (float) ($data['jam_tetap'] ?? 8);
        $jamTetapShift = (int) ($data['karyawan_tetap_shift'] ?? 0) * (int) ($data['hari_tetap_shift'] ?? 0) * (float) ($data['jam_tetap_shift'] ?? 8);
        $jamTidakTetap = (int) ($data['karyawan_tidak_tetap'] ?? 0) * (int) ($data['hari_tidak_tetap'] ?? 0) * (float) ($data['jam_tidak_tetap'] ?? 8);
        $jamTidakTetapShift = (int) ($data['karyawan_tidak_tetap_shift'] ?? 0) * (int) ($data['hari_tidak_tetap_shift'] ?? 0) * (float) ($data['jam_tidak_tetap_shift'] ?? 8);

        $totalJamOrang = $jamTetap + $jamTetapShift + $jamTidakTetap + $jamTidakTetapShift;

        $totalLembur = (float) ($data['lembur_tetap'] ?? 0)
            + (float) ($data['lembur_tetap_shift'] ?? 0)
            + (float) ($data['lembur_tidak_tetap'] ?? 0)
            + (float) ($data['lembur_tidak_tetap_shift'] ?? 0);

        $totalAbsensiJam = (float) ($data['cuti_jam'] ?? 0)
            + (float) ($data['ijin_jam'] ?? 0)
            + (float) ($data['sakit_jam'] ?? 0);

        $totalJamSeluruh = $totalJamOrang + $totalLembur - $totalAbsensiJam;

        return [
            'total_jam_kerja_orang' => round($totalJamOrang, 2),
            'total_lembur' => round($totalLembur, 2),
            'total_absensi_jam' => round($totalAbsensiJam, 2),
            'total_jam_kerja_seluruh' => round($totalJamSeluruh, 2),
        ];
    }
}
