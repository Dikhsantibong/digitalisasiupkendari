<?php

namespace Database\Factories;

use App\Models\K3PengusahaanHydrant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanHydrant>
 */
class K3PengusahaanHydrantFactory extends Factory
{
    protected $model = K3PengusahaanHydrant::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-12.06',
            'revisi' => '00',
            'tanggal_dokumen' => '23 SEPTEMBER 2019',
            'tanggal_periksa' => '13/8/2026',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
