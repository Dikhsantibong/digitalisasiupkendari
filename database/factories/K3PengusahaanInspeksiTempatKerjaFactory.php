<?php

namespace Database\Factories;

use App\Models\K3PengusahaanInspeksiTempatKerja;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3PengusahaanInspeksiTempatKerja>
 */
class K3PengusahaanInspeksiTempatKerjaFactory extends Factory
{
    protected $model = K3PengusahaanInspeksiTempatKerja::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'no_dokumen' => 'SMT-FM-AK3-12-01',
            'revisi' => '01',
            'tanggal_dokumen' => '23 September 2019',
            'tanggal_inspeksi' => '03 AGUSTUS 2026',
            'departemen' => 'PLN NUSANTARA POWER',
            'lokasi' => 'ULPLTD Poasia',
            'tim_inspektur' => 'ULPLTD Poasia',
            'ketua_tim' => 'Muh. Amin',
            'inspektur' => 'Azis',
            'catatan' => 'Inspeksi berkala tempat kerja dan fasilitas unit pembangkit.',
            'input_by' => User::factory(),
        ];
    }
}
