<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarJadwalCommissioningTest;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalCommissioningTestTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_commissioning_test_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.commissioning-test.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/commissioning-test/index')
            ->has('unit')
            ->has('rows', 14)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 8)
            ->where('filters.year', 2026)
            ->where('rows.0.section', 'PERSIAPAN')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.commissioning-test.index', [
            'unit_id' => $unit1->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.commissioning-test.index', [
            'unit_id' => $unit2->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_commissioning_test(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'catatan' => 'Semua peralatan siap diuji',
            'rows' => [
                [
                    'section' => 'PERSIAPAN',
                    'no_urut' => 1,
                    'kegiatan' => 'Periksa dan pastikan semua PMT Feeder dalam posisi OPEN',
                    'status' => 'SIAP OPERASI',
                    'pic' => 'AMIRULLAH',
                    'paraf' => 'OK',
                ],
                [
                    'section' => 'PARAREL GENERATOR',
                    'no_urut' => 1,
                    'kegiatan' => 'Operasikan salah satu mesin MaK',
                    'status' => 'ON',
                    'pic' => 'DOMINIKUS KOFI',
                    'paraf' => 'OK',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.commissioning-test.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('har_jadwal_commissioning_tests', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'section' => 'PERSIAPAN',
            'kegiatan' => 'Periksa dan pastikan semua PMT Feeder dalam posisi OPEN',
            'status' => 'SIAP OPERASI',
            'pic' => 'AMIRULLAH',
            'catatan' => 'Semua peralatan siap diuji',
        ]);

        $this->assertDatabaseHas('har_jadwal_commissioning_tests', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'section' => 'PARAREL GENERATOR',
            'kegiatan' => 'Operasikan salah satu mesin MaK',
            'status' => 'ON',
            'pic' => 'DOMINIKUS KOFI',
        ]);
    }

    public function test_can_download_pdf_jadwal_commissioning_test(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);

        HarJadwalCommissioningTest::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'section' => 'PERSIAPAN',
            'no_urut' => 1,
            'kegiatan' => 'Periksa saklar alat bantu',
            'status' => 'SIAP OPERASI',
            'pic' => 'AMIRULLAH',
            'paraf' => 'OK',
            'catatan' => 'Uji coba aman',
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.commissioning-test.pdf', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
