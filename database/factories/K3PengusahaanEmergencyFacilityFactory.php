<?php

namespace Database\Factories;

use App\Models\K3PengusahaanEmergencyFacility;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanEmergencyFacility>
 */
class K3PengusahaanEmergencyFacilityFactory extends Factory
{
    protected $model = K3PengusahaanEmergencyFacility::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'periode' => 'M1',
            'no_dokumen' => null,
            'revisi' => null,
            'tanggal_dokumen' => null,
            'halaman' => null,
            'grup' => 'Fire Pump',
            'no_urut' => 1,
            'nama_peralatan' => 'Fire Protection Jockey Pump',
            'jml_total' => '1',
            'jml_ready' => '1',
            'jml_not_ready' => '0',
            'persen_kesiapan' => '100%',
            'lokasi' => 'Fire Pump House',
            'kendala' => null,
            'tindak_lanjut' => null,
            'sort_order' => 1,
            'input_by' => User::factory(),
        ];
    }
}
