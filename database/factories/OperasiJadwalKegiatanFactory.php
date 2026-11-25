<?php

namespace Database\Factories;

use App\Models\OperasiJadwalKegiatan;
use App\Models\Unit;
use App\Support\JadwalKegiatanGroups;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperasiJadwalKegiatan>
 */
class OperasiJadwalKegiatanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => fake()->numberBetween(1, 12),
            'grup' => fake()->randomElement(JadwalKegiatanGroups::keys()),
            'no_urut' => 1,
            'kegiatan' => fake()->sentence(3),
            'target' => 20,
            'jadwal' => [],
            'keterangan' => null,
            'sort_order' => 0,
        ];
    }
}
