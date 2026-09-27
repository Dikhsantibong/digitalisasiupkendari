<?php

namespace Database\Factories;

use App\Models\K3PengusahaanAlatTanggapDarurat;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanAlatTanggapDarurat>
 */
class K3PengusahaanAlatTanggapDaruratFactory extends Factory
{
    protected $model = K3PengusahaanAlatTanggapDarurat::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-03.03',
            'revisi' => '01',
            'tanggal_dokumen' => '23 September 2019',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
