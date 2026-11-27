<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Http\Controllers\Operasi\JadwalCommissioningTestController;
use App\Models\Machine;
use App\Models\OperasiCommissioningTest;
use App\Models\Unit;
use App\Services\Monitoring\InputCatalog;
use App\Services\Notifications\ScheduleCatalog;
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

    public function test_unsaved_schedule_pulls_only_active_master_machines_of_the_selected_unit(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);
        $otherUnit = Unit::factory()->create(['name' => 'PLTD Unit Lain', 'is_active' => true]);

        $m2 = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'Mesin#02 / Cumm #7', 'is_active' => true]);
        $m1 = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'Mesin#01 / Cumm #6', 'is_active' => true]);
        Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'Mesin#99 Nonaktif', 'is_active' => false]);
        Machine::factory()->create(['unit_id' => $otherUnit->id, 'name' => 'Mesin Unit Lain', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.jadwal.commissioning-test.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/jadwal/commissioning-test/index')
            ->has('rows', 2)
            ->has('machines', 2)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.year', 2026)
            ->where('rows.0.machine_id', $m1->id)
            ->where('rows.0.nama_mesin', 'MESIN#01 / CUMM #6')
            ->where('rows.0.section', 'A. PEMBUATAN DATA TEKNIKS')
            ->where('rows.0.id', null)
            ->where('rows.1.machine_id', $m2->id)
            ->where('rows.1.nama_mesin', 'MESIN#02 / CUMM #7')
        );

        [$view, $data] = app(JadwalCommissioningTestController::class)->pdfView($unit, 2026);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('MESIN#01 / CUMM #6', $html);
        $this->assertStringNotContainsString('Mesin Unit Lain', $html);
        $this->assertStringNotContainsString('Mesin#99 Nonaktif', $html);
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit1);

        $this->actingAs($user)
            ->get(route('operasi.jadwal.commissioning-test.index', ['unit_id' => $unit1->id, 'year' => 2026]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('operasi.jadwal.commissioning-test.index', ['unit_id' => $unit2->id, 'year' => 2026]))
            ->assertForbidden();
    }

    public function test_can_save_retrieve_and_sync_master_machine_name(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'Mesin#01 / Cumm #6', 'is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                [
                    'machine_id' => $machine->id,
                    'no_urut' => 1,
                    'nama_mesin' => 'MESIN#01 / CUMM #6',
                    'section' => 'A. PEMBUATAN DATA TEKNIKS',
                    'beban_50' => ['5-2'],
                    'beban_75' => ['5-2'],
                    'beban_100' => ['5-2'],
                    'keterangan' => '',
                ],
                [
                    'machine_id' => null,
                    'no_urut' => 2,
                    'nama_mesin' => 'MESIN#02 CUSTOM',
                    'section' => 'A. PEMBUATAN DATA TEKNIKS',
                    'beban_50' => ['7-3'],
                    'beban_75' => ['7-3'],
                    'beban_100' => ['7-3'],
                    'keterangan' => '',
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->post(route('operasi.jadwal.commissioning-test.store'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('operasi_commissioning_tests', [
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
            'year' => 2026,
            'no_urut' => 1,
            'nama_mesin' => 'MESIN#01 / CUMM #6',
            'section' => 'A. PEMBUATAN DATA TEKNIKS',
        ]);

        // Updating the master machine name updates linked schedule display automatically.
        $machine->update(['name' => 'Mesin#01 / Cumm #6 Baru']);

        $this->actingAs($superAdmin)
            ->get(route('operasi.jadwal.commissioning-test.index', ['unit_id' => $unit->id, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.nama_mesin', 'MESIN#01 / CUMM #6 BARU')
                ->where('rows.1.nama_mesin', 'MESIN#02 CUSTOM')
            );
    }

    public function test_store_rejects_machine_from_another_unit(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);
        $otherUnit = Unit::factory()->create(['is_active' => true]);
        $foreignMachine = Machine::factory()->create(['unit_id' => $otherUnit->id, 'name' => 'Mesin Luar', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->post(route('operasi.jadwal.commissioning-test.store'), [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                [
                    'machine_id' => $foreignMachine->id,
                    'no_urut' => 1,
                    'nama_mesin' => 'MESIN LUAR',
                    'section' => 'A. PEMBUATAN DATA TEKNIKS',
                    'beban_50' => ['5-2'],
                    'beban_75' => [],
                    'beban_100' => [],
                ],
            ],
        ]);

        $response->assertSessionHasErrors('rows.0.machine_id');
    }

    public function test_can_download_pdf_and_render_layout_with_totals_and_recap(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);
        $m1 = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'MESIN#01 / CUMM #6', 'is_active' => true]);
        $m2 = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'MESIN#02 / CUMM #7', 'is_active' => true]);

        OperasiCommissioningTest::create([
            'unit_id' => $unit->id,
            'machine_id' => $m1->id,
            'year' => 2026,
            'no_urut' => 1,
            'nama_mesin' => 'MESIN#01 / CUMM #6',
            'section' => 'A. PEMBUATAN DATA TEKNIKS',
            'beban_50' => ['5-2'],
            'beban_75' => ['5-2'],
            'beban_100' => ['5-2'],
            'sort_order' => 0,
            'input_by' => $superAdmin->id,
        ]);

        OperasiCommissioningTest::create([
            'unit_id' => $unit->id,
            'machine_id' => $m2->id,
            'year' => 2026,
            'no_urut' => 2,
            'nama_mesin' => 'MESIN#02 / CUMM #7',
            'section' => 'A. PEMBUATAN DATA TEKNIKS',
            'beban_50' => ['7-3'],
            'beban_75' => ['7-3'],
            'beban_100' => ['7-3'],
            'sort_order' => 1,
            'input_by' => $superAdmin->id,
        ]);

        [$view, $data] = app(JadwalCommissioningTestController::class)->pdfView($unit, 2026);
        $html = view($view, $data)->render();

        $this->assertSame(6, $data['grandTotal']);
        $this->assertStringContainsString('JADWAL PELAKSANAAN COMMISSIONING TEST MESIN PEMBANGKIT', $html);
        $this->assertStringContainsString('UJI BEBAN', $html);
        $this->assertStringContainsString('NAMA MESIN PEMBANGKIT', $html);
        $this->assertStringContainsString('UJI BBN', $html);
        $this->assertStringContainsString('DATA', $html);
        $this->assertStringContainsString('TOTAL', $html);
        $this->assertStringContainsString('<td class="grand-total-cell">6</td>', $html);

        $response = $this->actingAs($superAdmin)->get(route('operasi.jadwal.commissioning-test.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    public function test_input_catalog_and_schedule_catalog_register_commissioning_test_as_yearly(): void
    {
        $inputEntry = collect(app(InputCatalog::class)->entries())
            ->firstWhere('table', 'operasi_commissioning_tests');
        $this->assertNotNull($inputEntry);
        $this->assertSame('year', $inputEntry['kind']);

        $scheduleEntry = collect(app(ScheduleCatalog::class)->tables())
            ->firstWhere('model', OperasiCommissioningTest::class);
        $this->assertNotNull($scheduleEntry);
        $this->assertSame('year', $scheduleEntry['period']);
        $this->assertSame('week', $scheduleEntry['match']);
        $this->assertSame(['beban_50', 'beban_75', 'beban_100'], $scheduleEntry['cells']);
    }
}
