<?php

namespace Database\Factories;

use App\Models\HarInstruksiKerja;
use App\Models\Unit;
use App\Support\HarIkTemplates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarInstruksiKerja>
 */
class HarInstruksiKerjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = HarIkTemplates::find('pm-1500-cummins');

        return [
            'unit_id' => Unit::factory(),
            'sort_order' => 0,
            'kop' => 'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI',
            'judul' => $template['judul'],
            'mesin' => $template['mesin'],
            'sections' => $template['sections'],
            'dibuat_jabatan' => 'Koordinator HAR',
            'dibuat_nama' => 'Koordinator',
            'disetujui_jabatan' => 'Project Leader',
            'disetujui_nama' => 'Project Leader',
        ];
    }
}
