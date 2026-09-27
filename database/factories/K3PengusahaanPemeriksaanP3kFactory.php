<?php

namespace Database\Factories;

use App\Models\K3PengusahaanPemeriksaanP3k;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanPemeriksaanP3k>
 */
class K3PengusahaanPemeriksaanP3kFactory extends Factory
{
    protected $model = K3PengusahaanPemeriksaanP3k::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-10.01',
            'revisi' => '01',
            'tanggal_dokumen' => '23 September 2019',
            'locations' => K3PengusahaanPemeriksaanP3k::DEFAULT_LOCATIONS,
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
