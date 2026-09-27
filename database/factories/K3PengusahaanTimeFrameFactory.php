<?php

namespace Database\Factories;

use App\Models\K3PengusahaanTimeFrame;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanTimeFrame>
 */
class K3PengusahaanTimeFrameFactory extends Factory
{
    protected $model = K3PengusahaanTimeFrame::class;

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
            'month' => 8,
            'no_urut' => 1,
            'uraian_pelaporan' => $this->faker->sentence(3),
            'pic' => 'K3 & Keamanan',
            'rencana' => [13],
            'realisasi' => [13],
            'keterangan' => null,
            'sort_order' => 1,
        ];
    }
}
