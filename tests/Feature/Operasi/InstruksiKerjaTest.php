<?php

namespace Tests\Feature\Operasi;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarInstruksiKerja;
use App\Models\OperasiInstruksiKerja;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Input Instruksi Kerja (IK) Operasi: the same IK input as Pemeliharaan (shared
 * BaseInstruksiKerjaController), with the Operasi model, templates and access.
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
            'judul' => "PEKERJAAN PM 1500 JAM\nMESIN CUMMINS KTA 50",
            'mesin' => 'Cummins KTA 50',
            'no_dokumen' => '',
            'tanggal' => null,
            'revisi' => '',
            'sections' => [
                ['judul' => 'ALAT', 'bernomor' => true, 'gaya' => 'angka', 'lanjut' => false, 'pengantar' => '', 'butir' => [
                    ['teks' => 'Kunci Ring Pas 1 Set', 'sub' => []],
                    ['teks' => '   ', 'sub' => []],
                ]],
                ['judul' => 'PROSEDUR PEKERJAAN', 'bernomor' => false, 'gaya' => 'panah', 'lanjut' => false, 'pengantar' => '', 'butir' => [
                    ['teks' => 'Ganti **Filter** bahan bakar', 'sub' => ['Tutup keran bahan bakar', ' ']],
                ]],
            ],
            'dibuat_jabatan' => 'Koordinator Operasi',
            'dibuat_nama' => 'Amirullah',
            'disetujui_jabatan' => 'Project Leader',
            'disetujui_nama' => 'Herwin',
        ], $overrides);
    }

    public function test_the_koordinator_opens_the_page_with_templates_and_default_signatories(): void
    {
        $unit = Unit::factory()->create();
        Employee::factory()->forUnit($unit)->create(['name' => 'Herwin Syahputra', 'position' => EmployeePosition::ProjectLeader->value, 'is_active' => true]);
        Employee::factory()->forUnit($unit)->create(['name' => 'Amirullah', 'position' => EmployeePosition::KoordinatorOperasi->value, 'is_active' => true]);
        $doc = OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id]);
        OperasiInstruksiKerja::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->get(route('operasi.input.instruksi-kerja.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/instruksi-kerja/index')
                ->has('docs', 1)
                ->where('docs.0.id', $doc->id)
                ->where('templates.1.key', 'start-mesin')
                ->where('signatories.dibuat_jabatan', 'Koordinator Operasi')
                ->where('signatories.dibuat_nama', 'Amirullah')
                ->where('signatories.disetujui_nama', 'Herwin Syahputra')
                ->where('can_write', true));
    }

    public function test_other_divisions_are_forbidden(): void
    {
        $unit = Unit::factory()->create();

        foreach ([RoleName::StafPemeliharaan, RoleName::Operator] as $role) {
            $user = $this->userWithRole($role, $unit);
            $this->actingAs($user)->get(route('operasi.input.instruksi-kerja.index'))->assertForbidden();
            $this->actingAs($user)->post(route('operasi.input.instruksi-kerja.store'), $this->payload($unit))->assertForbidden();
        }
    }

    public function test_saving_creates_then_updates_the_ik_and_drops_empty_points(): void
    {
        $unit = Unit::factory()->create();
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($koordinator)->post(route('operasi.input.instruksi-kerja.store'), $this->payload($unit))->assertRedirect();

        $doc = OperasiInstruksiKerja::query()->sole();
        $this->assertSame("PEKERJAAN PM 1500 JAM\nMESIN CUMMINS KTA 50", $doc->judul);
        $this->assertCount(1, $doc->sections[0]['butir']);
        $this->assertSame(['Tutup keran bahan bakar'], $doc->sections[1]['butir'][0]['sub']);
        $this->assertFalse($doc->sections[1]['bernomor']);

        $this->actingAs($koordinator)->post(route('operasi.input.instruksi-kerja.store'), $this->payload($unit, [
            'id' => $doc->id,
            'judul' => 'IK DIUBAH',
            'revisi' => '01',
        ]))->assertRedirect(route('operasi.input.instruksi-kerja.index', ['unit_id' => $unit->id, 'doc' => $doc->id]));

        $this->assertSame(1, OperasiInstruksiKerja::query()->count());
        $this->assertSame('IK DIUBAH', $doc->fresh()->judul);
        $this->assertSame('01', $doc->fresh()->revisi);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->post(route('operasi.input.instruksi-kerja.store'), $this->payload($unit, [
                'judul' => '',
                'sections' => [['judul' => '', 'bernomor' => true, 'gaya' => 'tabel', 'lanjut' => false, 'butir' => []]],
            ]))
            ->assertSessionHasErrors(['judul', 'sections.0.judul', 'sections.0.gaya']);

        $this->assertSame(0, OperasiInstruksiKerja::query()->count());
    }

    public function test_an_ik_of_another_unit_cannot_be_updated_or_deleted(): void
    {
        $unit = Unit::factory()->create();
        $other = OperasiInstruksiKerja::factory()->create();
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($koordinator)->post(route('operasi.input.instruksi-kerja.store'), $this->payload($unit, ['id' => $other->id]))->assertNotFound();
        $this->actingAs($koordinator)->delete(route('operasi.input.instruksi-kerja.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);
    }

    public function test_the_koordinator_deletes_an_ik(): void
    {
        $unit = Unit::factory()->create();
        $doc = OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->delete(route('operasi.input.instruksi-kerja.destroy', $doc))
            ->assertRedirect();

        $this->assertModelMissing($doc);
    }

    public function test_the_pdf_renders_one_or_all_ik_of_the_unit(): void
    {
        $unit = Unit::factory()->create();
        $doc = OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id]);
        OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id]);
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($koordinator)
            ->get(route('operasi.input.instruksi-kerja.pdf', ['unit_id' => $unit->id, 'doc' => $doc->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($koordinator)
            ->get(route('operasi.input.instruksi-kerja.pdf', ['unit_id' => $unit->id]))
            ->assertOk();

        $this->actingAs($koordinator)
            ->get(route('operasi.input.instruksi-kerja.pdf', ['unit_id' => $unit->id, 'doc' => 999999]))
            ->assertNotFound();
    }

    public function test_the_document_view_follows_the_mkp_layout(): void
    {
        $html = view('har.instruksi-kerja.document', ['doc' => [
            ...$this->payload(Unit::factory()->create()),
            'sections' => [
                ['judul' => 'ALAT', 'bernomor' => true, 'gaya' => 'angka', 'lanjut' => false, 'pengantar' => '', 'butir' => [['teks' => 'Kunci', 'sub' => []], ['teks' => 'Obeng', 'sub' => []]]],
                ['judul' => 'LANJUTAN', 'bernomor' => false, 'gaya' => 'angka', 'lanjut' => true, 'pengantar' => '', 'butir' => [['teks' => 'Ganti **Filter**', 'sub' => ['Tutup keran']]]],
            ],
        ]])->render();

        $this->assertStringContainsString('INSTRUKSI KERJA (IK)', $html);
        $this->assertStringContainsString('MESIN CUMMINS KTA 50', $html);
        $this->assertStringContainsString('3.</td>', $html);
        $this->assertStringContainsString('Ganti <strong>Filter</strong>', $html);
        $this->assertStringContainsString('Tutup keran', $html);
        $this->assertStringContainsString('Disetujui', $html);
        $this->assertStringContainsString('AMIRULLAH', $html);
    }

    public function test_the_team_leader_operasi_views_the_ik_read_only(): void
    {
        $unit = Unit::factory()->create();
        OperasiInstruksiKerja::factory()->create(['unit_id' => $unit->id]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($tl)
            ->get(route('operasi.input.instruksi-kerja.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('docs', 1)->where('can_write', false));
        $this->actingAs($tl)->post(route('operasi.input.instruksi-kerja.store'), $this->payload($unit))->assertForbidden();
    }

    public function test_operasi_and_pemeliharaan_ik_are_separate_libraries(): void
    {
        $unit = Unit::factory()->create();
        $har = HarInstruksiKerja::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->get(route('operasi.input.instruksi-kerja.index', ['unit_id' => $unit->id]))
            ->assertInertia(fn ($page) => $page->has('docs', 0));

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->delete(route('operasi.input.instruksi-kerja.destroy', $har->id))
            ->assertNotFound();
        $this->assertModelExists($har);
    }
}
