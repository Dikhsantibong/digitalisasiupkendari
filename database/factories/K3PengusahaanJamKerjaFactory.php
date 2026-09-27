<?php

namespace Database\Factories;

use App\Models\K3PengusahaanJamKerja;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanJamKerja>
 */
class K3PengusahaanJamKerjaFactory extends Factory
{
    protected $model = K3PengusahaanJamKerja::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $data = [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'FMZ-08.4.4.10',
            'tgl_berlaku' => '15 Okt 2014',
            'revisi' => '0.0',
            'halaman' => '1 dari 1',
            'karyawan_tetap' => 7,
            'karyawan_tetap_shift' => 0,
            'karyawan_tidak_tetap' => 29,
            'karyawan_tidak_tetap_shift' => 12,
            'hari_tetap' => 22,
            'jam_tetap' => 8.0,
            'lembur_tetap' => 0.0,
            'hari_tetap_shift' => 0,
            'jam_tetap_shift' => 8.0,
            'lembur_tetap_shift' => 0.0,
            'hari_tidak_tetap' => 22,
            'jam_tidak_tetap' => 8.0,
            'lembur_tidak_tetap' => 0.0,
            'hari_tidak_tetap_shift' => 31,
            'jam_tidak_tetap_shift' => 8.0,
            'lembur_tidak_tetap_shift' => 732.0,
            'cuti_orang' => 0,
            'cuti_hari' => 0,
            'cuti_jam' => 0.0,
            'ijin_orang' => 1,
            'ijin_hari' => 1,
            'ijin_jam' => 8.0,
            'sakit_orang' => 2,
            'sakit_hari' => 2,
            'sakit_jam' => 16.0,
            'catatan' => fake()->sentence(),
            'input_by' => User::factory(),
        ];

        $totals = K3PengusahaanJamKerja::computeTotals($data);

        return array_merge($data, $totals);
    }
}
