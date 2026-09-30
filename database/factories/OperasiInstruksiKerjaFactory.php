<?php

namespace Database\Factories;

use App\Models\OperasiInstruksiKerja;
use App\Models\Unit;
use App\Support\OperasiIkTemplates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperasiInstruksiKerja>
 */
class OperasiInstruksiKerjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = OperasiIkTemplates::find('start-mesin');

        return [
            'unit_id' => Unit::factory(),
            'sort_order' => 0,
            'kop' => 'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI',
            'judul' => $template['judul'],
            'mesin' => $template['mesin'],
            'sections' => $template['sections'],
            'dibuat_jabatan' => 'Koordinator Operasi',
            'dibuat_nama' => 'Koordinator',
            'disetujui_jabatan' => 'Project Leader',
            'disetujui_nama' => 'Project Leader',
        ];
    }
}
