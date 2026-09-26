<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarJadwalP0P5;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalP0P5Test extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_p0_p5_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);
        $machine = Machine::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'CUMMINS KTA-50-G8 #6',
            'serial_number' => 'F17K198896',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.p0-p5.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/p0-p5/index')
            ->has('unit')
            ->has('days')
            ->has('rows', 1)
            ->where('rows.0.name', 'CUMMINS KTA-50-G8 #6')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.p0-p5.index', [
            'unit_id' => $unit1->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.p0-p5.index', [
            'unit_id' => $unit2->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_p0_p5(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [
                [
                    'machine_id' => $machine->id,
                    'rencana' => ['6' => 'P3', '13' => 'P1', '20' => 'P2', '27' => 'P1'],
                    'realisasi' => ['6' => 'P3', '7' => 'P3', '13' => 'P1', '21' => 'P2', '27' => 'P1'],
                    'durasi' => ['6' => '8', '7' => '8', '13' => '4'],
                    'warna' => [
                        'rencana' => ['6' => 'green'],
                        'realisasi' => ['7' => 'yellow'],
                    ],
                    'operating_hours' => "P3: 1000 JAM\nP2: 1250 JAM",
                    'keterangan' => 'Pemeliharaan berjalan lancar sesuai SOP.',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.p0-p5.store'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('toast');

        $this->assertDatabaseHas('har_jadwal_p0_p5s', [
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
            'year' => 2026,
            'month' => 8,
            'operating_hours' => "P3: 1000 JAM\nP2: 1250 JAM",
            'keterangan' => 'Pemeliharaan berjalan lancar sesuai SOP.',
        ]);

        $saved = HarJadwalP0P5::where('machine_id', $machine->id)->first();
        $this->assertNotNull($saved);
        $this->assertEquals('P3', $saved->rencana['6']);
        $this->assertEquals('P3', $saved->realisasi['7']);
        $this->assertEquals('8', $saved->durasi['6']);
        $this->assertEquals('green', $saved->warna['rencana']['6']);
        $this->assertEquals('yellow', $saved->warna['realisasi']['7']);
    }

    public function test_can_download_pdf(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.p0-p5.pdf', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }
}
