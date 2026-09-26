<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarJadwalCommPeralatan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalCommissioningTestPeralatanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_commissioning_test_peralatan_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.commissioning-test-peralatan.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/commissioning-test-peralatan/index')
            ->has('unit')
            ->has('rows', 5)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.year', 2026)
            ->where('rows.0.nama_peralatan', 'COMPRESOR 1')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.commissioning-test-peralatan.index', [
            'unit_id' => $unit1->id,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.commissioning-test-peralatan.index', [
            'unit_id' => $unit2->id,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_commissioning_test_peralatan(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                [
                    'no_urut' => 1,
                    'nama_peralatan' => 'COMPRESOR 1',
                    'beban_50' => ['1-1', '6-2'],
                    'beban_75' => ['1-2'],
                    'beban_100' => ['1-3', '12-4'],
                    'keterangan' => 'Kondisi baik',
                ],
                [
                    'no_urut' => 2,
                    'nama_peralatan' => 'POMPA DRAINASE',
                    'beban_50' => ['3-1'],
                    'beban_75' => [],
                    'beban_100' => ['3-2'],
                    'keterangan' => '',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.commissioning-test-peralatan.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('har_jadwal_comm_peralatans', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'nama_peralatan' => 'COMPRESOR 1',
            'keterangan' => 'Kondisi baik',
        ]);

        $this->assertDatabaseHas('har_jadwal_comm_peralatans', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 2,
            'nama_peralatan' => 'POMPA DRAINASE',
        ]);

        $record = HarJadwalCommPeralatan::where('unit_id', $unit->id)->first();
        $this->assertEquals(['1-1', '6-2'], $record->beban_50);
        $this->assertEquals(['1-2'], $record->beban_75);
        $this->assertEquals(['1-3', '12-4'], $record->beban_100);
    }

    public function test_can_download_pdf_jadwal_commissioning_test_peralatan(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);

        HarJadwalCommPeralatan::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'nama_peralatan' => 'COMPRESOR 1',
            'beban_50' => ['1-1'],
            'beban_75' => ['1-2'],
            'beban_100' => ['1-3'],
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.commissioning-test-peralatan.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
