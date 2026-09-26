<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarJadwalPembuatanIk;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalPembuatanIkTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_pembuatan_ik_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.pembuatan-ik.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/pembuatan-ik/index')
            ->has('unit')
            ->has('rows', 30)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.year', 2026)
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.pembuatan-ik.index', [
            'unit_id' => $unit1->id,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.pembuatan-ik.index', [
            'unit_id' => $unit2->id,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_pembuatan_ik(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                [
                    'no_urut' => 1,
                    'instruksi_kerja' => 'IK Penggantian Filter Pelumas Mesin',
                    'pic_pembuat' => 'AMIRULLAH',
                    'rencana_bulan' => [1, 2],
                    'realisasi_bulan' => [1],
                    'keterangan' => 'Selesai bulan Januari',
                ],
                [
                    'no_urut' => 2,
                    'instruksi_kerja' => 'IK Overhaul Cylinder Head',
                    'pic_pembuat' => 'DOMINIKUS KOFI',
                    'rencana_bulan' => [3],
                    'realisasi_bulan' => [],
                    'keterangan' => null,
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.pembuatan-ik.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('har_jadwal_pembuatan_iks', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'instruksi_kerja' => 'IK Penggantian Filter Pelumas Mesin',
            'pic_pembuat' => 'AMIRULLAH',
        ]);

        $this->assertDatabaseHas('har_jadwal_pembuatan_iks', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'instruksi_kerja' => 'IK Overhaul Cylinder Head',
            'pic_pembuat' => 'DOMINIKUS KOFI',
        ]);

        $ik1 = HarJadwalPembuatanIk::where('instruksi_kerja', 'IK Penggantian Filter Pelumas Mesin')->first();
        $this->assertNotNull($ik1);
        $this->assertEquals([1, 2], $ik1->rencana_bulan);
        $this->assertEquals([1], $ik1->realisasi_bulan);
    }

    public function test_can_download_pdf_jadwal_pembuatan_ik(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);

        HarJadwalPembuatanIk::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'instruksi_kerja' => 'IK Pemeliharaan Injektor',
            'pic_pembuat' => 'AMIRULLAH',
            'rencana_bulan' => [1],
            'realisasi_bulan' => [1],
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.pembuatan-ik.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
