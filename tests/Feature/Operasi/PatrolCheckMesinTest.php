<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PatrolCheckMesinTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_patrol_check_mesin_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/input/patrol-check-mesin/index')
            ->has('unit')
            ->has('items', 29)
            ->where('daysInMonth', 31)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.year', 2026)
            ->where('filters.month', 8)
            ->where('filters.nama_mesin', 'Cummins #8')
            ->where('items.0.peralatan', 'Lube Oil Line Pipe')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit1->id,
            'year' => 2026,
            'month' => 8,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit2->id,
            'year' => 2026,
            'month' => 8,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_patrol_check_mesin(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
            'items' => [
                [
                    'no' => 1,
                    'system' => 'LUBRICATING SYSTEM',
                    'peralatan' => 'Lube Oil Line Pipe',
                    'checks' => ['1' => 'N', '2' => 'N', '3' => 'T'],
                ],
                [
                    'no' => 2,
                    'system' => 'LUBRICATING SYSTEM',
                    'peralatan' => 'Oil Line STC',
                    'checks' => ['1' => 'N', '2' => 'N'],
                ],
            ],
            'shift_pagi' => ['1' => 'D', '2' => 'D', '3' => 'C'],
            'shift_sore' => ['1' => 'C', '2' => 'C', '3' => 'A'],
            'shift_malam' => ['1' => 'B', '2' => 'A', '3' => 'D'],
            'catatan' => 'Hari ke-3 ditemukan rembesan oli pada lube line.',
        ];

        $response = $this->actingAs($superAdmin)->post(route('operasi.input.patrol-check-mesin.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('operasi_patrol_check_mesins', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
            'catatan' => 'Hari ke-3 ditemukan rembesan oli pada lube line.',
        ]);

        $record = OperasiPatrolCheckMesin::where('unit_id', $unit->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals('D', $record->shift_pagi['1'] ?? null);
        $this->assertEquals('N', $record->items[0]['checks']['1'] ?? null);
        $this->assertEquals('T', $record->items[0]['checks']['3'] ?? null);
    }

    public function test_can_download_pdf_patrol_check_mesin(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);

        OperasiPatrolCheckMesin::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
            'items' => [
                [
                    'no' => 1,
                    'system' => 'LUBRICATING SYSTEM',
                    'peralatan' => 'Lube Oil Line Pipe',
                    'checks' => ['1' => 'N'],
                ],
            ],
            'shift_pagi' => ['1' => 'D'],
            'shift_sore' => ['1' => 'C'],
            'shift_malam' => ['1' => 'B'],
            'catatan' => 'Kondisi normal',
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.patrol-check-mesin.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
