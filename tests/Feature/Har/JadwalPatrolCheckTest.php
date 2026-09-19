<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarJadwalPatrolCheck;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalPatrolCheckTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_jadwal_patrol_check_page_and_filters_operators(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);

        // Create Operator (should appear)
        $operator1 = Employee::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'AMIRULLAH',
            'position' => 'Operator',
            'is_active' => true,
        ]);
        $operator2 = Employee::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'RIDWAN B.',
            'position' => 'Operator',
            'is_active' => true,
        ]);

        // Create Manager UL, Staf, TL (should NOT appear)
        Employee::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'MANAGER JOHN',
            'position' => 'Manager UL',
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'STAF JANE',
            'position' => 'Staf Pemeliharaan',
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'TL BOB',
            'position' => 'Team Leader Pemeliharaan',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.patrol-check.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('har/jadwal/patrol-check/index')
            ->has('unit')
            ->has('days')
            ->has('target_working_days')
            ->has('rows', 2)
            ->where('rows.0.name', 'AMIRULLAH')
            ->where('rows.1.name', 'RIDWAN B.')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('har.jadwal.patrol-check.index', [
            'unit_id' => $unit1->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('har.jadwal.patrol-check.index', [
            'unit_id' => $unit2->id,
            'month' => 8,
            'year' => 2026,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_jadwal_patrol_check(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);
        $operator = Employee::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'AMIRULLAH',
            'position' => 'Operator',
            'is_active' => true,
        ]);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [
                [
                    'employee_id' => $operator->id,
                    'rencana' => ['3' => '1', '7' => '1', '13' => '1', '20' => '1', '27' => '1'],
                    'realisasi' => ['3' => '1', '7' => '1', '13' => '1', '20' => '1', '27' => '1'],
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('har.jadwal.patrol-check.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('har_jadwal_patrol_checks', [
            'unit_id' => $unit->id,
            'employee_id' => $operator->id,
            'month' => 8,
            'year' => 2026,
        ]);

        $record = HarJadwalPatrolCheck::query()->first();
        $this->assertNotNull($record);
        $this->assertEquals(['3' => '1', '7' => '1', '13' => '1', '20' => '1', '27' => '1'], $record->rencana);
        $this->assertEquals(['3' => '1', '7' => '1', '13' => '1', '20' => '1', '27' => '1'], $record->realisasi);

        // Retrieve and check via index
        $indexResponse = $this->actingAs($superAdmin)->get(route('har.jadwal.patrol-check.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn ($page) => $page
            ->where('rows.0.rencana.3', '1')
            ->where('rows.0.realisasi.3', '1')
        );
    }

    public function test_can_download_jadwal_patrol_check_pdf(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);
        $operator = Employee::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'AMIRULLAH',
            'position' => 'Operator',
            'is_active' => true,
        ]);

        HarJadwalPatrolCheck::create([
            'unit_id' => $unit->id,
            'employee_id' => $operator->id,
            'month' => 8,
            'year' => 2026,
            'rencana' => ['3' => '1', '7' => '1'],
            'realisasi' => ['3' => '1', '7' => '1'],
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('har.jadwal.patrol-check.pdf', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
