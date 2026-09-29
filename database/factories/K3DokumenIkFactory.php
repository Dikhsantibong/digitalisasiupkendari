<?php

namespace Database\Factories;

use App\Models\K3DokumenIk;
use App\Models\Unit;
use App\Support\K3IkTemplates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<K3DokumenIk>
 */
class K3DokumenIkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = K3IkTemplates::find('evakuasi');

        return [
            'unit_id' => Unit::factory(),
            'year' => 2026,
            'month' => 8,
            'sort_order' => 0,
            'sistem' => 'SMK3 LEVEL 3',
            'judul' => $template['judul'],
            'no_dokumen' => $template['no_dokumen'],
            'tanggal' => '2026-08-15',
            'revisi' => '00',
            'halaman' => '1/1',
            'sections' => $template['sections'],
        ];
    }
}
