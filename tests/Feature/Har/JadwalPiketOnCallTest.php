<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarJadwalPiketOnCall;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalPiketOnCallTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_piket_on_call_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.piket-on-call.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/piket-on-call/index')
            ->has('unit')
            ->has('days')
            ->has('rows')
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 8)
            ->where('filters.year', 2026)
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.piket-on-call.index', [
            'unit_id' => $unit1->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.piket-on-call.index', [
            'unit_id' => $unit2->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_piket_on_call(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [
                [
                    'nama' => 'AMIRULLAH',
                    'no_hp' => '085242411248',
                    'kategori' => 'HARMES',
                    'target' => 15,
                    'piket' => [1, 2, 8, 9, 15, 16],
                ],
                [
                    'nama' => 'RIDWAN BAHUDI',
                    'no_hp' => '082297111167',
                    'kategori' => 'HARLIS',
                    'target' => 15,
                    'piket' => [1, 8, 15, 22],
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.piket-on-call.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('har_jadwal_piket_on_calls', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'nama' => 'AMIRULLAH',
            'no_hp' => '085242411248',
            'kategori' => 'HARMES',
            'target' => 15,
        ]);

        $this->assertDatabaseHas('har_jadwal_piket_on_calls', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'nama' => 'RIDWAN BAHUDI',
            'no_hp' => '082297111167',
            'kategori' => 'HARLIS',
            'target' => 15,
        ]);

        $amirullah = HarJadwalPiketOnCall::where('nama', 'AMIRULLAH')->first();
        $this->assertNotNull($amirullah);
        $this->assertEquals([1, 2, 8, 9, 15, 16], $amirullah->piket);
    }

    public function test_can_download_pdf_jadwal_piket_on_call(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);

        HarJadwalPiketOnCall::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama' => 'AMIRULLAH',
            'no_hp' => '085242411248',
            'kategori' => 'HARMES',
            'target' => 15,
            'piket' => [1, 2, 8],
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.piket-on-call.pdf', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
