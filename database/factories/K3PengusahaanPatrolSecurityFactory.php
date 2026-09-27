<?php

namespace Database\Factories;

use App\Models\K3PengusahaanPatrolSecurity;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanPatrolSecurity>
 */
class K3PengusahaanPatrolSecurityFactory extends Factory
{
    protected $model = K3PengusahaanPatrolSecurity::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'judul' => 'PATROL CHECK SECURITY',
            'catatan' => 'Rekapitulasi patrol check security bulanan.',
            'input_by' => User::factory(),
        ];
    }
}
