<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3DocumentRecord;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanPengusahaanDocumentTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_laporan_permission_cannot_open_pengusahaan(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.laporan.pengusahaan.edit'))
            ->assertForbidden();
    }

    public function test_tl_sees_the_generated_pengusahaan_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.laporan.pengusahaan.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/laporan/pengusahaan')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->has('content')
                ->has('grid'),
            );
    }

    public function test_tl_can_save_pengusahaan_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.laporan.pengusahaan.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<div class="seg-cover-info">Laporan Pengusahaan diedit</div>',
        ])->assertRedirect();

        $record = K3DocumentRecord::query()
            ->where('unit_id', $unit->id)
            ->where('type', 'pengusahaan')
            ->firstOrFail();

        $this->assertSame('html', $record->format);
        $this->assertSame('pengusahaan', $record->type);
        $this->assertStringContainsString('Laporan Pengusahaan diedit', (string) $record->content_html);
    }

    public function test_tl_can_regenerate_pengusahaan_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.laporan.pengusahaan.regenerate'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
        ])->assertRedirect();

        $record = K3DocumentRecord::query()
            ->where('unit_id', $unit->id)
            ->where('type', 'pengusahaan')
            ->firstOrFail();

        $this->assertStringContainsString('cover-container', (string) $record->content_html);
        $this->assertStringContainsString('seg-tables-wide', (string) $record->content_html);
    }

    public function test_the_pengusahaan_document_exports_to_pdf_with_merged_orientations(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.laporan.pengusahaan.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertNotEmpty($response->getContent());
    }

    public function test_a_pengusahaan_document_cannot_be_opened_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->get(route('k3.laporan.pengusahaan.edit', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }
}
