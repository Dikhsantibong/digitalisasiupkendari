<?php

namespace Database\Factories;

use App\Models\K3PengusahaanMetodePengujian;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanMetodePengujian>
 */
class K3PengusahaanMetodePengujianFactory extends Factory
{
    protected $model = K3PengusahaanMetodePengujian::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 9,
            'no_urut' => (string) fake()->numberBetween(1, 10),
            'nama_peralatan' => fake()->randomElement(['Crane', 'Tangki Timbun', 'Instalasi Penyalur Petir', 'Bejana Tekan']),
            'no_pengesahan' => fake()->numerify('REG-####/DISNAKER'),
            'nama_kategori_alat' => fake()->word(),
            'uji_visual' => 'Memenuhi',
            'uji_fungsi' => 'Memenuhi',
            'uji_beban' => '-',
            'uji_hydro' => '-',
            'ndt' => 'Memenuhi',
            'uji_ultrasonic_thickness' => '-',
            'uji_ketahanan' => '-',
            'sertifikasi_terakhir' => '2024-01-01',
            'sertifikasi_ulang' => '2027-01-01',
            'keterangan' => fake()->sentence(),
            'sort_order' => 0,
            'input_by' => User::factory(),
        ];
    }
}
