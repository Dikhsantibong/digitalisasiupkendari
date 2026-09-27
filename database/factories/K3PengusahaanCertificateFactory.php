<?php

namespace Database\Factories;

use App\Models\K3PengusahaanCertificate;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanCertificate>
 */
class K3PengusahaanCertificateFactory extends Factory
{
    protected $model = K3PengusahaanCertificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'jenis' => fake()->randomElement(['Overhead Crane', 'Tangki Timbun HSD', 'Bejana Tekan']),
            'kapasitas' => fake()->randomElement(['5 Ton', '10 KL', '2 m3']),
            'lokasi' => fake()->word(),
            'uji_ulang_tanggal' => now()->addYear()->toDateString(),
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes): array => ['unit_id' => $unit->getKey()]);
    }
}
