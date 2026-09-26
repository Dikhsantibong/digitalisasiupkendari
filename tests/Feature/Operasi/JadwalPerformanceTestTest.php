<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\OperasiPerformanceTestMesin;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalPerformanceTestTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_performance_test_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.jadwal.performance-test.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/jadwal/performance-test/index')
            ->has('unit')
            ->has('rows', 3)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.year', 2026)
            ->where('rows.0.nama_mesin', 'MESIN#01 / CUMM #6')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('operasi.jadwal.performance-test.index', [
            'unit_id' => $unit1->id,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('operasi.jadwal.performance-test.index', [
            'unit_id' => $unit2->id,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_performance_test(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                [
                    'no_urut' => 1,
                    'nama_mesin' => 'MESIN#01 / CUMM #6',
                    'section' => 'A. PEMBUATAN DATA TEKNIKS',
                    'beban_50' => ['1-1', '4-4'],
                    'beban_75' => ['1-1', '4-4'],
                    'beban_100' => ['1-1', '4-4'],
                    'keterangan' => 'Performance test lancar',
                ],
                [
                    'no_urut' => 2,
                    'nama_mesin' => 'MESIN#02 / CUMM #7',
                    'section' => 'A. PEMBUATAN DATA TEKNIKS',
                    'beban_50' => ['2-1'],
                    'beban_75' => ['2-1'],
                    'beban_100' => ['2-1'],
                    'keterangan' => '',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('operasi.jadwal.performance-test.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('operasi_performance_test_mesins', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'nama_mesin' => 'MESIN#01 / CUMM #6',
            'section' => 'A. PEMBUATAN DATA TEKNIKS',
            'keterangan' => 'Performance test lancar',
        ]);

        $this->assertDatabaseHas('operasi_performance_test_mesins', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 2,
            'nama_mesin' => 'MESIN#02 / CUMM #7',
        ]);

        $record = OperasiPerformanceTestMesin::where('unit_id', $unit->id)->first();
        $this->assertEquals(['1-1', '4-4'], $record->beban_50);
        $this->assertEquals(['1-1', '4-4'], $record->beban_75);
        $this->assertEquals(['1-1', '4-4'], $record->beban_100);
    }

    public function test_can_download_pdf_jadwal_performance_test(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);

        OperasiPerformanceTestMesin::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'nama_mesin' => 'MESIN#01 / CUMM #6',
            'section' => 'A. PEMBUATAN DATA TEKNIKS',
            'beban_50' => ['1-1'],
            'beban_75' => ['1-1'],
            'beban_100' => ['1-1'],
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.jadwal.performance-test.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
