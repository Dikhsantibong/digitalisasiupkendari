<?php

namespace Database\Factories;

use App\Models\K3PengusahaanLaporanCctv;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanLaporanCctv>
 */
class K3PengusahaanLaporanCctvFactory extends Factory
{
    protected $model = K3PengusahaanLaporanCctv::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-14.01',
            'revisi' => '00',
            'tanggal_dokumen' => '23 SEPTEMBER 2019',
            'catatan' => 'Pemantauan CCTV rutin area unit pembangkit.',
            'input_by' => User::factory(),
        ];
    }
}
