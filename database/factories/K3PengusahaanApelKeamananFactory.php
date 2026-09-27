<?php

namespace Database\Factories;

use App\Models\K3PengusahaanApelKeamanan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanApelKeamanan>
 */
class K3PengusahaanApelKeamananFactory extends Factory
{
    protected $model = K3PengusahaanApelKeamanan::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-13.13',
            'revisi' => '00',
            'tanggal_dokumen' => '23 SEPTEMBER 2019',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
