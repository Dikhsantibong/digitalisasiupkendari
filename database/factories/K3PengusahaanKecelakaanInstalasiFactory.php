<?php

namespace Database\Factories;

use App\Models\K3PengusahaanKecelakaanInstalasi;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanKecelakaanInstalasi>
 */
class K3PengusahaanKecelakaanInstalasiFactory extends Factory
{
    protected $model = K3PengusahaanKecelakaanInstalasi::class;

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
            'nomor_keputusan' => '0251.P/DIR/2016',
            'catatan' => null,
            'input_by' => User::factory(),
        ];
    }
}
