<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\OperasiBlackstartJadwal;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalBlackstartTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_blackstart_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.jadwal.blackstart.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/jadwal/blackstart/index')
            ->has('unit')
            ->has('rows', 5)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.year', 2026)
            ->where('rows.0.uraian', 'ENGINE DIESEL GENSET (EDG)')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('operasi.jadwal.blackstart.index', [
            'unit_id' => $unit1->id,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('operasi.jadwal.blackstart.index', [
            'unit_id' => $unit2->id,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_blackstart(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                [
                    'no_urut' => 1,
                    'uraian' => 'ENGINE DIESEL GENSET (EDG)',
                    'pic' => 'Koord Operasi',
                    'rencana' => ['1-1', '6-2'],
                    'realisasi' => ['1-1'],
                    'keterangan' => 'Siap operasi',
                ],
                [
                    'no_urut' => 2,
                    'uraian' => 'SISTEM BATTERY',
                    'pic' => 'Koord Operasi',
                    'rencana' => ['2-1'],
                    'realisasi' => ['2-1'],
                    'keterangan' => '',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('operasi.jadwal.blackstart.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('operasi_blackstart_jadwals', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'uraian' => 'ENGINE DIESEL GENSET (EDG)',
            'pic' => 'Koord Operasi',
            'keterangan' => 'Siap operasi',
        ]);

        $this->assertDatabaseHas('operasi_blackstart_jadwals', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 2,
            'uraian' => 'SISTEM BATTERY',
        ]);

        $record = OperasiBlackstartJadwal::where('unit_id', $unit->id)->first();
        $this->assertEquals(['1-1', '6-2'], $record->rencana);
        $this->assertEquals(['1-1'], $record->realisasi);
    }

    public function test_can_download_pdf_jadwal_blackstart(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);

        OperasiBlackstartJadwal::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'no_urut' => 1,
            'uraian' => 'ENGINE DIESEL GENSET (EDG)',
            'pic' => 'Koord Operasi',
            'rencana' => ['1-1'],
            'realisasi' => ['1-1'],
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.jadwal.blackstart.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
