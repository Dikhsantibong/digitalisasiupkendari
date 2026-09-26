<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarJadwalHarian;
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

    public function test_super_admin_can_view_jadwal_harian_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.harian.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/harian/index')
            ->has('unit')
            ->has('days', 31)
            ->has('rows', 12)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 8)
            ->where('filters.year', 2026)
            ->where('rows.0.kegiatan', 'Absensi')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.harian.index', [
            'unit_id' => $unit1->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.harian.index', [
            'unit_id' => $unit2->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_harian(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [
                [
                    'no_urut' => 1,
                    'kegiatan' => 'Absensi',
                    'target' => 20,
                    'rencana_count' => 20,
                    'jadwal' => [3, 4, 5, 6, 7, 10, 11, 12, 13, 14],
                    'keterangan' => 'Lengkap 10 hari pertama',
                ],
                [
                    'no_urut' => 2,
                    'kegiatan' => 'Daily Meeting / Safety Breefing',
                    'target' => 20,
                    'rencana_count' => 20,
                    'jadwal' => [3, 4, 5, 6, 7],
                    'keterangan' => null,
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.harian.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('har_jadwal_harians', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'kegiatan' => 'Absensi',
            'target' => 20,
            'realisasi_count' => 10,
        ]);

        $this->assertDatabaseHas('har_jadwal_harians', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'kegiatan' => 'Daily Meeting / Safety Breefing',
            'target' => 20,
            'realisasi_count' => 5,
        ]);

        $record = HarJadwalHarian::where('kegiatan', 'Absensi')->first();
        $this->assertNotNull($record);
        $this->assertEquals([3, 4, 5, 6, 7, 10, 11, 12, 13, 14], $record->jadwal);
    }

    public function test_can_download_pdf_jadwal_harian(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);

        HarJadwalHarian::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'no_urut' => 1,
            'kegiatan' => 'Pengecekan level oli dan air pendingin',
            'target' => 20,
            'rencana_count' => 20,
            'realisasi_count' => 5,
            'jadwal' => [3, 4, 5, 6, 7],
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.harian.pdf', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
