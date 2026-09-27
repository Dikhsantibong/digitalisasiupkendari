<?php

namespace Database\Factories;

use App\Models\K3PengusahaanBukuTamu;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanBukuTamu>
 */
class K3PengusahaanBukuTamuFactory extends Factory
{
    protected $model = K3PengusahaanBukuTamu::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-06.04',
            'revisi' => '01',
            'tanggal_dokumen' => '23 SEPTEMBER 2019',
            'catatan' => 'Laporan mutasi buku tamu periode bulanan unit pembangkit.',
            'input_by' => User::factory(),
        ];
    }
}
