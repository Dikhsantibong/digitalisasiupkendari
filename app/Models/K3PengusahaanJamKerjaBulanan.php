<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Database\Factories\K3PengusahaanJamKerjaBulananFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class K3PengusahaanJamKerjaBulanan extends Model
{
    use BelongsToUnit;

    /** @use HasFactory<K3PengusahaanJamKerjaBulananFactory> */
    use HasFactory;

    protected $table = 'k3_pengusahaan_jam_kerja_bulanans';

    protected $fillable = [
        'unit_id',
        'year',
        'month',
        'no_dokumen',
        'tgl_berlaku',
        'revisi',
        'halaman',
        'jam_kerja_komulatif_bulan_lalu',
        'karyawan_tetap',
        'karyawan_tetap_shift',
        'karyawan_tidak_tetap',
        'karyawan_tidak_tetap_shift',
        'jumlah_karyawan',
        'hari_kerja',
        'jam_kerja_standart',
        'jam_kerja_standart_karyawan',
        'jam_kerja_lembur_karyawan',
        'jam_kerja_seluruh_karyawan',
        'jam_absensi_karyawan',
        'jam_kerja_realisasi_karyawan',
        'jam_kerja_komulatif_bulan_ini',
        'catatan',
        'input_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jam_kerja_komulatif_bulan_lalu' => 'float',
            'karyawan_tetap' => 'integer',
            'karyawan_tetap_shift' => 'integer',
            'karyawan_tidak_tetap' => 'integer',
            'karyawan_tidak_tetap_shift' => 'integer',
            'jumlah_karyawan' => 'integer',
            'hari_kerja' => 'integer',
            'jam_kerja_standart' => 'float',
            'jam_kerja_standart_karyawan' => 'float',
            'jam_kerja_lembur_karyawan' => 'float',
            'jam_kerja_seluruh_karyawan' => 'float',
            'jam_absensi_karyawan' => 'float',
            'jam_kerja_realisasi_karyawan' => 'float',
            'jam_kerja_komulatif_bulan_ini' => 'float',
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
     * Compute calculated fields according to the official FMZ-08.4.4.11 formula.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function computeRows(array $data): array
    {
        $tetap = (int) ($data['karyawan_tetap'] ?? 0);
        $tetapShift = (int) ($data['karyawan_tetap_shift'] ?? 0);
        $tidakTetap = (int) ($data['karyawan_tidak_tetap'] ?? 0);
        $tidakTetapShift = (int) ($data['karyawan_tidak_tetap_shift'] ?? 0);
        $jumlahKaryawan = $tetap + $tetapShift + $tidakTetap + $tidakTetapShift;

        $hariKerja = (int) ($data['hari_kerja'] ?? 0);
        $jamStandart = $hariKerja * 8.0;

        $jamStandartKaryawan = (float) ($data['jam_kerja_standart_karyawan'] ?? 0);
        $jamLembur = (float) ($data['jam_kerja_lembur_karyawan'] ?? 0);
        $jamSeluruh = $jamStandartKaryawan + $jamLembur;

        $jamAbsensi = (float) ($data['jam_absensi_karyawan'] ?? 0);
        $jamRealisasi = $jamSeluruh - $jamAbsensi;

        $komulatifLalu = (float) ($data['jam_kerja_komulatif_bulan_lalu'] ?? 0);
        $komulatifIni = $komulatifLalu + $jamRealisasi;

        return [
            'jumlah_karyawan' => $jumlahKaryawan,
            'jam_kerja_standart' => round($jamStandart, 2),
            'jam_kerja_seluruh_karyawan' => round($jamSeluruh, 2),
            'jam_kerja_realisasi_karyawan' => round($jamRealisasi, 2),
            'jam_kerja_komulatif_bulan_ini' => round($komulatifIni, 2),
        ];
    }
}
