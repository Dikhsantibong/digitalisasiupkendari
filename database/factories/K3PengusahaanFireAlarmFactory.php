<?php

namespace Database\Factories;

use App\Models\K3PengusahaanFireAlarm;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanFireAlarm>
 */
class K3PengusahaanFireAlarmFactory extends Factory
{
    protected $model = K3PengusahaanFireAlarm::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-12.06',
            'revisi' => '00',
            'tanggal_dokumen' => '23 SEPTEMBER 2019',
            'tanggal_periksa' => '12/08/2026',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
