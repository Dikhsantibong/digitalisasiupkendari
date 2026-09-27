<?php

namespace Database\Factories;

use App\Models\K3PengusahaanApat;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanApat>
 */
class K3PengusahaanApatFactory extends Factory
{
    protected $model = K3PengusahaanApat::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-12.04',
            'revisi' => '01',
            'tanggal_dokumen' => '23 September 2019',
            'tanggal_inspeksi' => '12/8/2026',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
