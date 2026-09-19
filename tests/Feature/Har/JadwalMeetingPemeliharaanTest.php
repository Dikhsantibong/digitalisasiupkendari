<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarJadwalMeetingPemeliharaan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalMeetingPemeliharaanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_meeting_pemeliharaan_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.meeting-pemeliharaan.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/meeting-pemeliharaan/index')
            ->has('unit')
            ->has('days')
            ->has('rows', 1)
            ->where('rows.0.uraian', 'Jadwal Meeting Pemeliharaan')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.meeting-pemeliharaan.index', [
            'unit_id' => $unit1->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.meeting-pemeliharaan.index', [
            'unit_id' => $unit2->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_meeting_pemeliharaan(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [
                [
                    'uraian' => 'Jadwal Meeting Pemeliharaan',
                    'target' => 1,
                    'rencana' => ['31' => 1],
                    'realisasi' => ['31' => 1],
                    'keterangan' => 'Rapat evaluasi akhir bulan.',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.meeting-pemeliharaan.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('har_jadwal_meeting_pemeliharaans', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'uraian' => 'Jadwal Meeting Pemeliharaan',
            'target' => 1,
        ]);

        $record = HarJadwalMeetingPemeliharaan::query()->first();
        $this->assertNotNull($record);
        $this->assertEquals(['31' => 1], $record->rencana);
        $this->assertEquals(['31' => 1], $record->realisasi);

        // Retrieve via index
        $indexResponse = $this->actingAs($superAdmin)->get(route('har.jadwal.meeting-pemeliharaan.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn ($page) => $page
            ->where('rows.0.rencana.31', 1)
            ->where('rows.0.realisasi.31', 1)
            ->where('rows.0.target', 1)
        );
    }

    public function test_can_download_jadwal_meeting_pemeliharaan_pdf(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);

        HarJadwalMeetingPemeliharaan::create([
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'uraian' => 'Jadwal Meeting Pemeliharaan',
            'target' => 1,
            'rencana' => ['31' => 1],
            'realisasi' => ['31' => 1],
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.meeting-pemeliharaan.pdf', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
