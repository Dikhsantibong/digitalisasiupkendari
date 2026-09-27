<?php

namespace Database\Factories;

use App\Models\K3PengusahaanAparApab;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanAparApab>
 */
class K3PengusahaanAparApabFactory extends Factory
{
    protected $model = K3PengusahaanAparApab::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-12.03',
            'revisi' => '01',
            'tanggal_dokumen' => '23 SEPTEMBER 2019',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
