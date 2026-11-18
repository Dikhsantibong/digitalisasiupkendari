<?php

namespace Database\Factories;

use App\Models\PdmInstruksiKerja;
use App\Models\Unit;
use App\Support\PdmIkTemplates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdmInstruksiKerja>
 */
class PdmInstruksiKerjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = PdmIkTemplates::find('vibrasi');

        return [
            'unit_id' => Unit::factory(),
            'sort_order' => 0,
            'kop' => 'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI',
            'judul' => $template['judul'],
            'mesin' => $template['mesin'],
            'sections' => $template['sections'],
            'dibuat_jabatan' => 'Koordinator PDM',
            'dibuat_nama' => 'Koordinator',
            'disetujui_jabatan' => 'Team Leader Pemeliharaan',
            'disetujui_nama' => 'Team Leader',
        ];
    }
}
