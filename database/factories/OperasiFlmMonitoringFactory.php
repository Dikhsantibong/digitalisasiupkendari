<?php

namespace Database\Factories;

use App\Models\OperasiFlmMonitoring;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperasiFlmMonitoring>
 */
class OperasiFlmMonitoringFactory extends Factory
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
            'no_urut' => 1,
            'mesin' => fake()->randomElement(['Fuel Transfer Pump', 'Sensor Coolant Temp', 'Muffler Cummins #6']),
            'tanggal' => '2026-08-20',
            'masalah' => 'Terdapat kebocoran BBM',
            'kondisi_awal' => ['bersihkan', 'kencangkan'],
            'kondisi_akhir' => 'Ditadah',
            'catatan' => 'Ditindaklanjuti oleh tim HAR',
            'status' => 'close',
        ];
    }
}
