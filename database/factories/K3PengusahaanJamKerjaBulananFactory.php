<?php

namespace Database\Factories;

use App\Models\K3PengusahaanJamKerjaBulanan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanJamKerjaBulanan>
 */
class K3PengusahaanJamKerjaBulananFactory extends Factory
{
    protected $model = K3PengusahaanJamKerjaBulanan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $data = [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'FMZ-08.4.4.11',
            'tgl_berlaku' => '15 Okt 2014',
            'revisi' => '0.0',
            'halaman' => '1 dari 1',
            'jam_kerja_komulatif_bulan_lalu' => 10020.0,
            'karyawan_tetap' => 7,
            'karyawan_tetap_shift' => 0,
            'karyawan_tidak_tetap' => 29,
            'karyawan_tidak_tetap_shift' => 12,
            'hari_kerja' => 22,
            'jam_kerja_standart_karyawan' => 9312.0,
            'jam_kerja_lembur_karyawan' => 732.0,
            'jam_absensi_karyawan' => 24.0,
            'catatan' => fake()->sentence(),
            'input_by' => User::factory(),
        ];

        $computed = K3PengusahaanJamKerjaBulanan::computeRows($data);

        return array_merge($data, $computed);
    }
}
