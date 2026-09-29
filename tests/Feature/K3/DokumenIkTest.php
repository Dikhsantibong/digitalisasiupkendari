<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3DokumenIk;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Input Dokumen IK K3 (Akses 1): IK documents written from a template, printed
 * in the official layout and attached to the Laporan K3.
 */
class DokumenIkTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_lists_the_documents_of_the_period_and_the_templates(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        K3DokumenIk::factory()->create(['unit_id' => $unit->id]);
        K3DokumenIk::factory()->create(['unit_id' => $unit->id, 'month' => 9]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->get(route('k3.input.dokumen-ik.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/dokumen-ik/index')
                ->has('docs', 1)
                ->where('docs.0.judul', 'INSTRUKSI KERJA PELAKSANAAN EVAKUASI')
                ->where('docs.0.sections.0.judul', 'ALAT')
                ->where('templates.0.key', 'kosong')
                ->where('can_write', true));
    }

    public function test_saving_creates_then_updates_a_document(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);
        $payload = [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'sistem' => 'SMK3 LEVEL 3',
            'judul' => 'INSTRUKSI KERJA PENGGUNAAN APAR',
            'no_dokumen' => 'SMK3/IK/06-11-07',
            'tanggal' => '2026-08-20',
            'revisi' => '00',
            'halaman' => '1/1',
            'sections' => [
                ['judul' => 'ALAT', 'gaya' => 'butir', 'pengantar' => '', 'butir' => ['APAR', '', '  Sarung tangan  ']],
                ['judul' => 'LANGKAH PELAKSANAAN', 'gaya' => 'huruf', 'pengantar' => 'Bila terjadi kebakaran :', 'butir' => ['Tarik pin', 'Arahkan nozzle']],
            ],
        ];

        $response = $this->actingAs($user)->post(route('k3.input.dokumen-ik.store'), $payload)->assertSessionHasNoErrors();
        $doc = K3DokumenIk::query()->sole();
        $response->assertRedirect(route('k3.input.dokumen-ik.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'doc' => $doc->id]));
        $this->assertSame(['APAR', 'Sarung tangan'], $doc->sections[0]['butir']);
        $this->assertSame('Bila terjadi kebakaran :', $doc->sections[1]['pengantar']);

        $this->actingAs($user)->post(route('k3.input.dokumen-ik.store'), [...$payload, 'id' => $doc->id, 'revisi' => '01'])->assertSessionHasNoErrors();
        $this->assertSame(1, K3DokumenIk::query()->count());
        $this->assertSame('01', $doc->fresh()->revisi);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->post(route('k3.input.dokumen-ik.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'sistem' => 'SMK3 LEVEL 3', 'judul' => '',
                'sections' => [['judul' => '', 'gaya' => 'miring', 'butir' => []]],
            ])
            ->assertSessionHasErrors(['judul', 'sections.0.judul', 'sections.0.gaya']);
    }

    public function test_the_pdf_prints_one_or_all_documents_and_delete_removes_one(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);
        $doc = K3DokumenIk::factory()->create(['unit_id' => $unit->id]);
        K3DokumenIk::factory()->create(['unit_id' => $unit->id, 'judul' => 'INSTRUKSI KERJA PENGGUNAAN APD']);
        $query = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->get(route('k3.input.dokumen-ik.pdf', [...$query, 'doc' => $doc->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get(route('k3.input.dokumen-ik.pdf', $query))->assertOk();
        $this->actingAs($user)->get(route('k3.input.dokumen-ik.pdf', [...$query, 'month' => 1]))->assertNotFound();

        $this->actingAs($user)->delete(route('k3.input.dokumen-ik.destroy', $doc))->assertRedirect();
        $this->assertModelMissing($doc);
    }

    public function test_the_documents_are_attached_to_the_laporan_k3(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);
        K3DokumenIk::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($user)->get(route('k3.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('content', fn (string $html): bool => str_contains($html, 'INSTRUKSI KERJA PELAKSANAAN EVAKUASI')
                && str_contains($html, 'DOKUMEN IK K3 LINGKUNGAN PEMBANGKIT')
                && str_contains($html, 'Tim yang telah ditunjuk sebagai pelaksana evakuasi')
                && str_contains($html, 'Terlampir (1 IK)')));

        $this->actingAs($user)->get(route('k3.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))->assertOk();
    }

    public function test_other_divisions_and_units_are_forbidden(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $foreign = Unit::factory()->create(['is_active' => true]);
        $doc = K3DokumenIk::factory()->create(['unit_id' => $foreign->id]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))->get(route('k3.input.dokumen-ik.index'))->assertForbidden();
        $koordinator = $this->userWithRole(RoleName::KoordinatorK3, $unit);
        $this->actingAs($koordinator)->get(route('k3.input.dokumen-ik.index', ['unit_id' => $foreign->id]))->assertForbidden();
        $this->actingAs($koordinator)->delete(route('k3.input.dokumen-ik.destroy', $doc))->assertForbidden();
    }
}
