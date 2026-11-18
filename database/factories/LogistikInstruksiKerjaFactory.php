<?php

namespace Database\Factories;

use App\Models\LogistikInstruksiKerja;
use App\Models\Unit;
use App\Support\LogistikIkTemplates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogistikInstruksiKerja>
 */
class LogistikInstruksiKerjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = LogistikIkTemplates::find('penerimaan-material');

        return [
            'unit_id' => Unit::factory(),
            'sort_order' => 0,
            'kop' => 'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI',
            'judul' => $template['judul'],
            'mesin' => $template['mesin'],
            'sections' => $template['sections'],
            'dibuat_jabatan' => 'Koordinator Logistik',
            'dibuat_nama' => 'Koordinator',
            'disetujui_jabatan' => 'Team Leader Pemeliharaan',
            'disetujui_nama' => 'Team Leader',
        ];
    }
}
