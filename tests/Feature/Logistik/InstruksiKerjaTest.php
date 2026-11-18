<?php

namespace Tests\Feature\Logistik;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LogistikInstruksiKerja;
use App\Models\OperasiInstruksiKerja;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Input Instruksi Kerja (IK) Logistik & Gudang: the shared IK input
 * (BaseInstruksiKerjaController + the visual A4 editor) with the Logistik & Gudang
 * model, templates and access; made by the Koordinator Logistik, approved by the TL
 * Pemeliharaan.
 */
class InstruksiKerjaTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Unit $unit, array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $unit->id,
            'id' => null,
            'kop' => 'JASA PENDUKUNG TEKNIS 6 SITE -KIT UP KENDARI',
            'judul' => "PENERIMAAN MATERIAL\nGUDANG PEMBANGKIT",
            'mesin' => '',
            'no_dokumen' => '',
            'tanggal' => '2026-11-10',
            'revisi' => '',
            'sections' => [
                ['judul' => 'ALAT', 'bernomor' => true, 'gaya' => 'angka', 'lanjut' => false, 'pengantar' => '', 'butir' => [
                    ['teks' => 'Sarung Tangan', 'sub' => []],
                    ['teks' => ' ', 'sub' => []],
                ]],
                ['judul' => 'PROSEDUR', 'bernomor' => false, 'gaya' => 'panah', 'lanjut' => false, 'pengantar' => '', 'butir' => [
                    ['teks' => 'Catat **hasil**', 'sub' => ['Pada formulir', '']],
                ]],
            ],
            'dibuat_jabatan' => 'Koordinator Logistik',
            'dibuat_nama' => 'Andi',
            'disetujui_jabatan' => 'Team Leader Pemeliharaan',
            'disetujui_nama' => 'Budi',
        ], $overrides);
    }

    public function test_the_koordinator_opens_the_page_with_templates_and_tl_pemeliharaan_as_approver(): void
    {
        $unit = Unit::factory()->create();
        Employee::factory()->forUnit($unit)->create(['name' => 'Budi Santoso', 'position' => EmployeePosition::TeamLeaderPemeliharaan->value, 'is_active' => true]);
        Employee::factory()->forUnit($unit)->create(['name' => 'Andi Wijaya', 'position' => EmployeePosition::KoordinatorLogistik->value, 'is_active' => true]);
        $doc = LogistikInstruksiKerja::factory()->create(['unit_id' => $unit->id]);
        LogistikInstruksiKerja::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorLogistik, $unit))
            ->get(route('logistik.input.instruksi-kerja.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('logistik/input/instruksi-kerja/index')
                ->has('docs', 1)
                ->where('docs.0.id', $doc->id)
                ->where('templates.1.key', 'penerimaan-material')
                ->where('signatories.dibuat_jabatan', 'Koordinator Logistik')
                ->where('signatories.dibuat_nama', 'Andi Wijaya')
                ->where('signatories.disetujui_jabatan', 'Team Leader Pemeliharaan')
                ->where('signatories.disetujui_nama', 'Budi Santoso')
                ->where('can_write', true));
    }

    public function test_other_divisions_are_forbidden(): void
    {
        $unit = Unit::factory()->create();

        foreach ([RoleName::KoordinatorOperasi, RoleName::Operator] as $role) {
            $user = $this->userWithRole($role, $unit);
            $this->actingAs($user)->get(route('logistik.input.instruksi-kerja.index'))->assertForbidden();
            $this->actingAs($user)->post(route('logistik.input.instruksi-kerja.store'), $this->payload($unit))->assertForbidden();
        }
    }

    public function test_saving_creates_then_updates_the_ik_and_drops_empty_points(): void
    {
        $unit = Unit::factory()->create();
        $koordinator = $this->userWithRole(RoleName::KoordinatorLogistik, $unit);

        $this->actingAs($koordinator)->post(route('logistik.input.instruksi-kerja.store'), $this->payload($unit))->assertRedirect();

        $doc = LogistikInstruksiKerja::query()->sole();
        $this->assertSame("PENERIMAAN MATERIAL\nGUDANG PEMBANGKIT", $doc->judul);
        $this->assertCount(1, $doc->sections[0]['butir']);
        $this->assertSame(['Pada formulir'], $doc->sections[1]['butir'][0]['sub']);

        $this->actingAs($koordinator)->post(route('logistik.input.instruksi-kerja.store'), $this->payload($unit, ['id' => $doc->id, 'judul' => 'IK DIUBAH']))
            ->assertRedirect(route('logistik.input.instruksi-kerja.index', ['unit_id' => $unit->id, 'doc' => $doc->id]));

        $this->assertSame(1, LogistikInstruksiKerja::query()->count());
        $this->assertSame('IK DIUBAH', $doc->fresh()->judul);
    }

    public function test_the_pdf_renders_and_another_unit_or_module_ik_stays_out_of_reach(): void
    {
        $unit = Unit::factory()->create();
        $doc = LogistikInstruksiKerja::factory()->create(['unit_id' => $unit->id]);
        $otherUnit = LogistikInstruksiKerja::factory()->create();
        $operasi = OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id]);
        $koordinator = $this->userWithRole(RoleName::KoordinatorLogistik, $unit);

        $this->actingAs($koordinator)
            ->get(route('logistik.input.instruksi-kerja.pdf', ['unit_id' => $unit->id, 'doc' => $doc->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($koordinator)->post(route('logistik.input.instruksi-kerja.store'), $this->payload($unit, ['id' => $otherUnit->id]))->assertNotFound();
        $this->actingAs($koordinator)->delete(route('logistik.input.instruksi-kerja.destroy', $otherUnit))->assertForbidden();
        // A separate library: the Operasi IK of the unit is not listed here.
        $this->actingAs($koordinator)->get(route('logistik.input.instruksi-kerja.index', ['unit_id' => $unit->id]))
            ->assertInertia(fn ($page) => $page->has('docs', 1)->where('docs.0.id', $doc->id));
        $this->assertModelExists($otherUnit);
        $this->assertModelExists($operasi);

        $this->actingAs($koordinator)->delete(route('logistik.input.instruksi-kerja.destroy', $doc))->assertRedirect();
        $this->assertModelMissing($doc);
    }

    public function test_the_pdf_preview_renders_the_unsaved_ik(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorLogistik, $unit))
            ->postJson(route('logistik.input.instruksi-kerja.preview'), $this->payload($unit))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
