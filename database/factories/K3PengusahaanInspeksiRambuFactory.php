<?php

namespace Database\Factories;

use App\Models\K3PengusahaanInspeksiRambu;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanInspeksiRambu>
 */
class K3PengusahaanInspeksiRambuFactory extends Factory
{
    protected $model = K3PengusahaanInspeksiRambu::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-07.01',
            'revisi' => '01',
            'tanggal_dokumen' => '23 September 2019',
            'tanggal_inspeksi' => '01/09/2026',
            'catatan' => 'Inspeksi berkala rambu K3 area pembangkit.',
            'input_by' => User::factory(),
        ];
    }
}
