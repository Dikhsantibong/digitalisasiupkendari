<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalHarianTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_pdm_jadwal_harian_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('pdm.jadwal.harian.index', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('pdm/jadwal/harian/index')
            ->has('unit')
            ->has('days', 30)
            ->has('rows')
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 9)
            ->where('filters.year', 2026)
        );
    }

    public function test_can_save_pdm_jadwal_harian_records(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);

        $rows = [
            [
                'kategori' => 'I. MESIN',
                'is_category_header' => true,
                'no_urut' => 'I',
                'kegiatan' => 'MESIN',
                'target' => 22,
                'rencana_count' => 22,
                'jadwal' => [],
                'keterangan' => null,
                'sort_order' => 0,
            ],
            [
                'kategori' => 'I. MESIN',
                'is_category_header' => false,
                'no_urut' => '1',
                'kegiatan' => 'Pengukuran Vibrasi',
                'target' => 22,
                'rencana_count' => 22,
                'jadwal' => [1, 2, 3, 4, 5],
                'keterangan' => 'Kondisi normal',
                'sort_order' => 1,
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('pdm.jadwal.harian.store'), [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
            'rows' => $rows,
        ]);

        $response->assertRedirect(route('pdm.jadwal.harian.index', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $this->assertDatabaseHas('pdm_jadwal_harians', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
            'kegiatan' => 'Pengukuran Vibrasi',
            'target' => 22,
            'realisasi_count' => 5,
        ]);
    }

    public function test_can_export_pdm_jadwal_harian_pdf(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('pdm.jadwal.harian.pdf', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $response->assertOk();
    }
}
