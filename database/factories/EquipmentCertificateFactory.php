<?php

namespace Database\Factories;

use App\Models\EquipmentCertificate;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentCertificate>
 */
class EquipmentCertificateFactory extends Factory
{
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

    /** A certificate whose retest date has passed. */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['uji_ulang_tanggal' => now()->subDays(10)->toDateString()]);
    }

    /** A certificate whose retest date is within the warning window. */
    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes): array => ['uji_ulang_tanggal' => now()->addDays(30)->toDateString()]);
    }
}
