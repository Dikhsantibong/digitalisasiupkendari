<?php

namespace Database\Factories;

use App\Models\K3PengusahaanKecelakaanMasyarakat;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanKecelakaanMasyarakat>
 */
class K3PengusahaanKecelakaanMasyarakatFactory extends Factory
{
    protected $model = K3PengusahaanKecelakaanMasyarakat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'is_nihil' => true,
            'lampiran_teks' => 'Lampiran 1 Keputusan Direksi PT PLN (Persero)',
            'nomor_keputusan' => 'Nomor :0252.P/DIR/2016',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
