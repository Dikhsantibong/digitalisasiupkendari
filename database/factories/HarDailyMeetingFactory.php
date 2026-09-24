<?php

namespace Database\Factories;

use App\Models\HarDailyMeeting;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarDailyMeeting>
 */
class HarDailyMeetingFactory extends Factory
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
            'month' => 8,
            'tanggal' => '2026-08-31',
            'acara' => 'Meeting HAR',
            'waktu' => '16.00',
            'tempat' => fake()->city(),
            'peserta' => [['nama' => fake()->name(), 'asal' => 'PT MKP', 'jabatan' => 'Operator']],
            'eviden' => [],
        ];
    }
}
