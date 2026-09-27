<?php

namespace Database\Factories;

use App\Models\K3PengusahaanInventarisApd;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanInventarisApd>
 */
class K3PengusahaanInventarisApdFactory extends Factory
{
    protected $model = K3PengusahaanInventarisApd::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 9,
            'no_dokumen' => 'SMT-FM-AK3-01.01',
            'revisi' => '01',
            'tanggal_dokumen' => '23 September 2019',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
