<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\OperasiChecklistCommissioningMesin;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ChecklistCommissioningMesinTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_checklist_commissioning_mesin_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.checklist-commissioning-mesin.index', [
            'unit_id' => $unit->id,
            'tanggal' => '2026-09-26',
            'nama_mesin' => 'Cummins #8',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/input/checklist-commissioning-mesin/index')
            ->has('unit')
            ->has('rows', 9)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.tanggal', '2026-09-26')
            ->where('filters.nama_mesin', 'Cummins #8')
            ->where('rows.0.section', 'PERSIAPAN')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('operasi.input.checklist-commissioning-mesin.index', [
            'unit_id' => $unit1->id,
            'tanggal' => '2026-09-26',
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('operasi.input.checklist-commissioning-mesin.index', [
            'unit_id' => $unit2->id,
            'tanggal' => '2026-09-26',
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_checklist_commissioning_mesin(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'tanggal' => '2026-09-26',
            'nama_mesin' => 'Cummins #8',
            'rows' => [
                [
                    'no' => 1,
                    'section' => 'PERSIAPAN',
                    'kegiatan' => 'Periksa PMT Feeder',
                    'status' => 'ON',
                    'pic' => 'Andi',
                    'paraf' => 'OK',
                ],
                [
                    'no' => 2,
                    'section' => 'PARAREL GENERATOR',
                    'kegiatan' => 'Operasikan mesin',
                    'status' => 'SIAP OPERASI',
                    'pic' => 'Budi',
                    'paraf' => 'OK',
                ],
            ],
            'catatan' => 'Semua kesiapan telah diverifikasi lapangan.',
        ];

        $response = $this->actingAs($superAdmin)->post(route('operasi.input.checklist-commissioning-mesin.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('operasi_checklist_commissioning_mesins', [
            'unit_id' => $unit->id,
            'tanggal' => '2026-09-26 00:00:00',
            'nama_mesin' => 'Cummins #8',
            'catatan' => 'Semua kesiapan telah diverifikasi lapangan.',
        ]);

        $record = OperasiChecklistCommissioningMesin::where('unit_id', $unit->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals('ON', $record->rows[0]['status'] ?? null);
        $this->assertEquals('Andi', $record->rows[0]['pic'] ?? null);
    }

    public function test_can_download_pdf_checklist_commissioning_mesin(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);

        OperasiChecklistCommissioningMesin::create([
            'unit_id' => $unit->id,
            'tanggal' => '2026-09-26',
            'nama_mesin' => 'Cummins #8',
            'rows' => [
                [
                    'no' => 1,
                    'section' => 'PERSIAPAN',
                    'kegiatan' => 'Periksa PMT Feeder',
                    'status' => 'ON',
                    'pic' => 'Andi',
                    'paraf' => 'OK',
                ],
            ],
            'catatan' => 'Catatan tes',
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.checklist-commissioning-mesin.pdf', [
            'unit_id' => $unit->id,
            'tanggal' => '2026-09-26',
            'nama_mesin' => 'Cummins #8',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
