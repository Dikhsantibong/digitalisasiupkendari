<?php

namespace Database\Factories;

use App\Models\K3PengusahaanEvaluasiPengujian;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanEvaluasiPengujian>
 */
class K3PengusahaanEvaluasiPengujianFactory extends Factory
{
    protected $model = K3PengusahaanEvaluasiPengujian::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'no_urut' => (string) fake()->numberBetween(1, 10),
            'nama_kategori_alat' => fake()->randomElement(['Crane', 'Tangki Timbun', 'Penyalur Petir']),
            'jenis' => fake()->words(2, true),
            'kapasitas' => fake()->randomElement(['5 ton', '25.000 Liter', '100.000 Liter', '-']),
            'temuan_sertifikat' => fake()->sentence(),
            'progres_bulan' => [1, 2, 3, 4, 5],
            'keterangan' => fake()->sentence(),
            'sort_order' => 0,
            'input_by' => User::factory(),
        ];
    }
}
